"""
detection.py
دالة كشف محاولات SQLi بطريقة محسّنة:
- نظام نقاط (score-based)
- تسجيل (logging) لحالات الاشتباه
- قوائم بيضاء (whitelists) لكل حقل قابلة للتعديل
- قابلية إضافة أنماط جديدة مع أوزان لكل نمط
- ترجع dict تحتوي على القرار، النقاط، والأنماط المطابقة
"""

import re
import logging
from typing import Dict, List, Optional

# ---------- إعدادات السجل (يمكن تعديل الوجهة / المستوى حسب الحاجة) ----------
logger = logging.getLogger("sqli_detector")
if not logger.handlers:
    handler = logging.StreamHandler()
    fmt = logging.Formatter(
        "[%(asctime)s] %(levelname)s - %(message)s", datefmt="%Y-%m-%d %H:%M:%S"
    )
    handler.setFormatter(fmt)
    logger.addHandler(handler)
logger.setLevel(logging.INFO)  # غيّر إلى DEBUG عند الحاجة للمزيد من التفاصيل

# ---------- أنماط الكشف مع وزن لكل نمط (الوزن يؤثر على النقاط المضافة) ----------
PATTERN_WEIGHTS = [
    # (pattern, description, weight)
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

# نركّب Pattern واحد لكل مجموعة للتسريع (مع IGNORECASE و DOTALL)
COMPILED_PATTERNS = [
    (re.compile(pat, re.IGNORECASE | re.DOTALL), desc, weight) for pat, desc, weight in PATTERN_WEIGHTS
]

# ---------- قوائم بيضاء (whitelists) بسيطة حسب الحقل ----------
# إذا الحقل يطابق أي نمط من هذه القوائم نخفض حساسيته (مثلاً أسماء المستخدمين التي تقبل أحرف معينة)
WHITELIST_PATTERNS: Dict[str, List[re.Pattern]] = {
    "username": [re.compile(r"^[A-Za-z0-9_.-]{3,30}$")],  # أسماء مستخدمين عادية
    "email": [re.compile(r"^[\w\.-]+@[\w\.-]+\.\w{2,}$")],
    # أضف حقول أخرى حسب الحاجة
}

# ---------- معلمات النظام ----------
DEFAULT_THRESHOLD = 8  # إذا تجاوزت النقاط هذه، نعتبرها محاولة هجوم (قابل للتعديل)
MIN_INPUT_LENGTH_TO_CHECK = 2  # تجاهل المدخلات القصيرة جداً لتقليل false positives
MAX_POINTS_PER_FIELD = 50  # حد أعلى لمنع قيم نقاط مبالغ بها


# ---------- الدالة الرئيسية للكشف ----------
def detect_sqli(
    inputs: Dict[str, Optional[str]],
    threshold: int = DEFAULT_THRESHOLD,
    whitelist_patterns: Optional[Dict[str, List[re.Pattern]]] = None,
) -> Dict:
    """
    Parameters:
      - inputs: قاموس الحقول المدخلة من المستخدم، مثال:
          {"username": "admin", "password": "pass' OR 1=1;", "email": "..."}
      - threshold: نقاط العتبة لتقرير أن هذا هجوم.
      - whitelist_patterns: (اختياري) يمكن تمرير قوائم بيضاء مخصصة.
    Returns:
      dict يحتوي على:
        - decision: "clean" أو "suspicious"
        - score: مجموع النقاط
        - per_field: تفاصيل لكل حقل (matched_patterns, field_score, reason)
        - matched_total: قائمة بالأنماط المطابقة عبر الحقول
    """
    if whitelist_patterns is None:
        whitelist_patterns = WHITELIST_PATTERNS

    result = {
        "decision": "clean",
        "score": 0,
        "per_field": {},
        "matched_total": [],
    }

    total_score = 0
    matched_total = []

    for field, raw_value in inputs.items():
        value = (raw_value or "").strip()
        field_info = {"value_sample": value[:100], "matched_patterns": [], "field_score": 0, "notes": []}

        # تجاهل القيم القصيرة
        if len(value) < MIN_INPUT_LENGTH_TO_CHECK:
            field_info["notes"].append("ignored_too_short")
            result["per_field"][field] = field_info
            continue

        # تحقق القائمة البيضاء: إن طابق القاعدة البيضاء، خفّض التأثير
        whitelist_hit = False
        if field in whitelist_patterns:
            for wpat in whitelist_patterns[field]:
                if wpat.fullmatch(value):
                    whitelist_hit = True
                    field_info["notes"].append("whitelist_match")
                    break

        # فحص الأنماط
        for comp_pat, desc, weight in COMPILED_PATTERNS:
            if comp_pat.search(value):
                # إذا كانت هناك مطابقة، أضف الوصف والوزن
                field_info["matched_patterns"].append({"pattern_desc": desc, "weight": weight})
                field_info["field_score"] += weight
                matched_total.append({"field": field, "pattern": desc, "weight": weight})

        # تطبيق تخفيض إن كانت القائمة البيضاء قد طابقت
        if whitelist_hit and field_info["field_score"] > 0:
            # نخفّض النقاط بنسبة 50% للحقول الموثوقة
            field_info["notes"].append("whitelist_applied: -50%")
            field_info["field_score"] = int(field_info["field_score"] * 0.5)

        # حد أقصى للنقاط لحقل واحد
        if field_info["field_score"] > MAX_POINTS_PER_FIELD:
            field_info["notes"].append("capped_by_max_field_points")
            field_info["field_score"] = MAX_POINTS_PER_FIELD

        total_score += field_info["field_score"]
        result["per_field"][field] = field_info

    # حد أعلى إجمالي (حماية ضد تجميع نقاط مبالغ فيها)
    if total_score > MAX_POINTS_PER_FIELD * len(inputs):
        total_score = MAX_POINTS_PER_FIELD * len(inputs)

    # قرار الاستجابة
    result["score"] = total_score
    result["matched_total"] = matched_total
    if total_score >= threshold:
        result["decision"] = "suspicious"
        # سجل الحدث مع تفاصيل كافية لتحليل لاحق
        logger.warning(
            "SQLi detected: score=%s threshold=%s matched=%s",
            total_score,
            threshold,
            [{"field": m["field"], "pattern": m["pattern"], "weight": m["weight"]} for m in matched_total],
        )
    else:
        result["decision"] = "clean"
        logger.debug("No significant SQLi patterns: score=%s", total_score)

    return result


# ---------- مثال للاستخدام السريع ----------
if __name__ == "__main__":
    sample_inputs = {
        "username": "normalUser",
        "password": "password' OR '1'='1",
        "email": "attacker@example.com",
    }
    out = detect_sqli(sample_inputs)
    from pprint import pprint

    pprint(out)
