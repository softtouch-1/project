
import re
import json
import os
import logging
from typing import Dict, List, Optional
from flask import Flask, request, Response, jsonify
import requests

# ------------------ إعداد اللوق ------------------
logger = logging.getLogger("sqli_proxy_combined")
if not logger.handlers:
    handler = logging.StreamHandler()
    fmt = logging.Formatter("[%(asctime)s] %(levelname)s - %(message)s", "%Y-%m-%d %H:%M:%S")
    handler.setFormatter(fmt)
    logger.addHandler(handler)
logger.setLevel(logging.INFO)

# ------------------ دالة الكشف (محسّنة) ------------------
PATTERN_WEIGHTS = [
    (r"\bUNION\b", "UNION keyword", 5),
    (r"\bSELECT\b", "SELECT keyword", 4),
    (r"\bINSERT\b", "INSERT keyword", 4),
    (r"\bUPDATE\b", "UPDATE keyword", 4),
    (r"\bDELETE\b", "DELETE keyword", 4),
    (r"\bDROP\b", "DROP keyword", 6),
    (r"(--|#|/\*)", "SQL comment", 3),
    (r"\bOR\b\s+\d+\s*=\s*\d+", "boolean tautology OR 1=1 style", 6),
    (r"('\s*or\s*')", "quoted OR pattern", 5),
    (r"sleep\s*\(", "time-based (sleep)", 5),
    (r"benchmark\s*\(", "time-based (benchmark)", 5),
    (r"0x[0-9a-fA-F]{2,}", "hex input", 2),
    (r"\bINFORMATION_SCHEMA\b", "information_schema access", 7),
    (r"\bINTO\b\s+OUTFILE\b", "INTO OUTFILE", 8),
    (r"\bLOAD_FILE\b", "LOAD_FILE", 8),
    (r"exec\s+\w+\s*\(", "exec / stored procedure call", 6),
]

COMPILED_PATTERNS = [(re.compile(p, re.IGNORECASE | re.DOTALL), d, w) for p, d, w in PATTERN_WEIGHTS]

WHITELIST_PATTERNS: Dict[str, List[re.Pattern]] = {
    "username": [re.compile(r"^[A-Za-z0-9_.-]{3,30}$")],
    "email": [re.compile(r"^[\w\.-]+@[\w\.-]+\.\w{2,}$")],
}

DEFAULT_THRESHOLD = 8
MIN_INPUT_LENGTH_TO_CHECK = 2
MAX_POINTS_PER_FIELD = 50


def detect_sqli(
    inputs: Dict[str, Optional[str]],
    threshold: int = DEFAULT_THRESHOLD,
    whitelist_patterns: Optional[Dict[str, List[re.Pattern]]] = None,
) -> Dict:
    """
    ترجع dict مع القرار ('clean' أو 'suspicious')، score، matched_total، per_field.
    """
    if whitelist_patterns is None:
        whitelist_patterns = WHITELIST_PATTERNS

    result = {"decision": "clean", "score": 0, "per_field": {}, "matched_total": []}
    total_score = 0
    matched_total = []

    for field, raw_value in inputs.items():
        value = (raw_value or "").strip()
        field_info = {"value_sample": value[:200], "matched_patterns": [], "field_score": 0, "notes": []}

        if len(value) < MIN_INPUT_LENGTH_TO_CHECK:
            field_info["notes"].append("ignored_too_short")
            result["per_field"][field] = field_info
            continue

        whitelist_hit = False
        if field in whitelist_patterns:
            for wpat in whitelist_patterns[field]:
                if wpat.fullmatch(value):
                    whitelist_hit = True
                    field_info["notes"].append("whitelist_match")
                    break

        for comp_pat, desc, weight in COMPILED_PATTERNS:
            if comp_pat.search(value):
                field_info["matched_patterns"].append({"pattern_desc": desc, "weight": weight})
                field_info["field_score"] += weight
                matched_total.append({"field": field, "pattern": desc, "weight": weight})

        if whitelist_hit and field_info["field_score"] > 0:
            field_info["notes"].append("whitelist_applied:-50%")
            field_info["field_score"] = int(field_info["field_score"] * 0.5)

        if field_info["field_score"] > MAX_POINTS_PER_FIELD:
            field_info["notes"].append("capped_by_max_field_points")
            field_info["field_score"] = MAX_POINTS_PER_FIELD

        total_score += field_info["field_score"]
        result["per_field"][field] = field_info

    # حماية ضد نقاط مبالغ فيها
    max_total = MAX_POINTS_PER_FIELD * max(1, len(inputs))
    if total_score > max_total:
        total_score = max_total

    result["score"] = int(total_score)
    result["matched_total"] = matched_total

    if result["score"] >= threshold:
        result["decision"] = "suspicious"
        logger.warning(
            "SQLi detected: score=%s threshold=%s matched=%s",
            result["score"],
            threshold,
            [{"field": m["field"], "pattern": m["pattern"], "weight": m["weight"]} for m in matched_total],
        )
    else:
        result["decision"] = "clean"
        logger.debug("No significant SQLi: score=%s", result["score"])

    return result


# ------------------ Flask Proxy ------------------
app = Flask(__name__)

VULN_APP_BASE = os.environ.get("VULN_APP_BASE", "http://127.0.0.1:80")
THRESHOLD = int(os.environ.get("DETECT_THRESHOLD", str(DEFAULT_THRESHOLD)))
INCIDENT_LOG_FILE = os.environ.get("INCIDENT_LOG_FILE", "sqli_incidents.log")


def log_incident(record: dict):
    try:
        with open(INCIDENT_LOG_FILE, "a", encoding="utf-8") as f:
            f.write(json.dumps(record, ensure_ascii=False) + "\n")
    except Exception as e:
        logger.exception("Failed to write incident to file: %s", e)


def extract_client_ip(req):
    xf = req.headers.get("X-Forwarded-For", "")
    if xf:
        return xf.split(",")[0].strip()
    return req.remote_addr or "unknown"


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "proxy": True}), 200


@app.route("/login", methods=["GET", "POST"])
def login_proxy():
    if request.method == "GET":
        return """
        <h3>Proxy Login (test)</h3>
        <form method="post">
          <input name="username" placeholder="username"/><br/>
          <input name="password" placeholder="password"/><br/>
          <button type="submit">Login</button>
        </form>
        """, 200

    form = request.form.to_dict(flat=True)
    if not form and request.is_json:
        try:
            json_body = request.get_json(silent=True) or {}
            form = {k: str(v) for k, v in json_body.items()}
        except Exception:
            form = {}

    inputs = {k: v for k, v in form.items()}

    client_ip = extract_client_ip(request)
    user_agent = request.headers.get("User-Agent", "unknown")

    detection_result = detect_sqli(inputs, threshold=THRESHOLD)

    if detection_result.get("decision") == "suspicious":
        incident = {
            "event": "sqli_detected",
            "client_ip": client_ip,
            "user_agent": user_agent,
            "inputs_sample": {k: (v[:200] + "...") if len(v) > 200 else v for k, v in inputs.items()},
            "score": detection_result.get("score"),
            "threshold": THRESHOLD,
            "matched_total": detection_result.get("matched_total"),
            "per_field": detection_result.get("per_field"),
        }
        logger.warning("Blocked request: %s", json.dumps(incident, ensure_ascii=False))
        log_incident(incident)
        return jsonify({"error": "Request blocked: suspicious activity detected."}), 403

    # مرّر الطلب للتطبيق الضعيف
    try:
        target_url = VULN_APP_BASE.rstrip("/") + "/login"
        headers = {k: v for k, v in request.headers.items() if k.lower() not in ("host", "content-length")}
        if request.content_type and "application/json" in request.content_type.lower():
            resp = requests.post(target_url, json=request.get_json(silent=True), headers=headers, timeout=10)
        else:
            resp = requests.post(target_url, data=request.form, headers=headers, timeout=10)
    except requests.RequestException as e:
        logger.exception("Error forwarding to vuln app: %s", e)
        return jsonify({"error": "Bad gateway forwarding to backend."}), 502

    response_headers = [(name, value) for (name, value) in resp.headers.items() if name.lower() not in ("content-encoding", "transfer-encoding", "connection")]
    return Response(resp.content, status=resp.status_code, headers=response_headers)


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    logger.info("Starting proxy on 0.0.0.0:%s forwarding to %s (threshold=%s)", port, VULN_APP_BASE, THRESHOLD)
    app.run(host="0.0.0.0", port=port, debug=False)
