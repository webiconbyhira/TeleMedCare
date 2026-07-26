#!/usr/bin/env python3
# TeleCare — rule-based health assistant (trained responses with follow-up questions)
# Called from chatbot.php via stdin JSON: {"message": "..."}

import json
import re
import sys

EMERGENCY_KEYS = [
    "emergency", "urgent", "1122", "115", "ambulance", "heart attack",
    "heartattack", "cant breathe", "can't breathe", "unconscious",
    "severe bleeding", "stroke", "choking", "dying", "critical",
    "ہنگامی", "فوری", "ایمرجنسی", "ایمبولینس", "دل کا دورہ", "دماغ کا دورہ",
    "بے ہوش", "تشویش", "سانس نہیں آ رہا", 'چہرہ ٹیڈھا', 'بات نہ کر پانا',
    'بہت زیادہ خون بہنا', 'گھبراہٹ', 'سخت الرجک',
]

EMERGENCY_REPLY = (
    "This may be a medical emergency. Please seek immediate medical care or contact emergency services. "
    "Do NOT wait for an online appointment. TeleCare is for non-emergency booking only. "
    "Do NOT take any medicine for suspected emergency symptoms."
)

SYMPTOM_RULES = [
    {
        "keys": ["type 2 diabetes", "type2 diabetes", "diabetes", "diabetic", "blood sugar", "sugar disease", "ذیابیطس", "شوگر", "بلڈ شوگر", "پیا لگنا", "پیشاب", "ثک", "zyada pyaas", "bar bar peshab", "thakan"],
        "reply_en": (
            "Type 2 Diabetes: Symptoms include excessive thirst (zyada pyaas), frequent urination (bar bar peshab), and fatigue (thakan). "
            "Monitor blood sugar regularly, follow a healthy diet (avoid sweets and white bread), walk daily. "
            "Common medicines: Metformin or Insulin — but ALWAYS consult a diabetologist for proper prescription. "
            "Search 'diabetes' on TeleCare to find a specialist in your city."
        ),
        "reply_ur": (
            "Type 2 Diabetes: symptoms ہیں زیادہ پیا لگنا، بار بار پیشاب اور تھکائی۔ "
            "Blood sugar چیک کریں باقاعدہ، healthy diet follow کریں، میٹی یا چپاتی avoid کریں، روزانہ walk کریں۔ "
            "Medicines: Metformin یا Insulin — لیکن ہمیشہ Diabetologist سے مشورہ کریں۔ TeleCare par search کریں۔"
        ),
        "followUp_en": "How long have you had diabetes? Are you currently taking any medicine? What is your latest blood sugar reading (fasting/after meal)?",
        "followUp_ur": "ذیابیطس کتنے سال سے ہے؟ کیا آپ کوئی دوائی لے رہے ہیں؟ آپ کا پہلے/پس کے کھانے کا بلڈ شوگر کتنا ہے؟",
        "condition": "diabetes"
    },
    {
        "keys": ["hypertension", "high bp", "high blood pressure", "bp", "blood pressure", "blpressure", "بلڈ پریشر", "ہائی پریشر", "لوی پریشر", "sar dard", "chakkar", "سر درد", "چکر"],
        "reply_en": (
            "Hypertension (High BP): Symptoms can include headache (sar dard) and dizziness (chakkar), but many patients have NO symptoms at all. "
            "Monitor BP regularly, reduce salt intake, avoid stress, walk daily. "
            "Common medicines: Amlodipine or Telmisartan — but ALWAYS consult a cardiologist or physician for correct dosage. "
            "Search 'bp' on TeleCare to find a specialist."
        ),
        "reply_ur": (
            "Hypertension (ہائی بلڈ پریشر): Symptoms ہو سکتے ہیں سر درد اور چکر، لیکن کئی patients کے پاس کوئی symptoms نہیں ہوتے۔ "
            "BP باقاعدہ چیک کریں، نمک کم کریں، stress avoid کریں، walk کریں۔ "
            "Medicines: Amlodipine یا Telmisartan — لیکن ہمیشہ Cardiologist سے مشورہ کریں۔ TeleCare par search کریں۔"
        ),
        "followUp_en": "How long have you had high BP? Are you on any BP medicine currently? What is your latest BP reading?",
        "followUp_ur": "ہائی بلڈ پریشر کتنے سال سے ہے؟ کیا آپ بلڈ پریشر کی دوائی لے رہے ہیں؟ آپ کا آخری پریشر کتنا تھا؟",
        "condition": "hypertension"
    },
    {
        "keys": ["asthma", "breathing problem", "difficulty breathing", "دمہ", "سانس کی تکلیف", "sans ka phoolna", "wheezing"],
        "reply_en": (
            "Asthma: Symptoms include difficulty breathing (sans ka phoolna) and wheezing (high-pitched sound while breathing). "
            "Use inhaler (Salbutamol/Ventolin) as needed, avoid dust, smoke, and cold air. "
            "ALWAYS get prescription from a pulmonologist before using any inhaler regularly. "
            "Search 'asthma' on TeleCare for long-term management plan."
        ),
        "reply_ur": (
            "Asthma (دمہ): Symptoms ہیں سانس لینے میں دشواری اور سانس میں آواز آنا۔ "
            "Inhaler (Salbutamol/Ventolin) استعمال کریں، دھول، smoke اور cold air سے بچیں۔ "
            "باقاعدہ Inhaler استعمال کرنے سے پہلے Pulmonologist سے consultation ضروری ہے۔ TeleCare par specialist dhoond sakte hain۔"
        ),
        "followUp_en": "How long have you had asthma? Do you use an inhaler regularly? Any recent attacks or ER visits?",
        "followUp_ur": "دمہ کتنے سال سے ہے؟ کیا آپ Inhaler باقاعدہ استعمال کرتے ہیں؟ حالیہ کئی حملے ہوئے ہیں یا ER آیا؟",
        "condition": "asthma"
    },
    {
        "keys": ["copd", "chronic obstructive pulmonary", "khansi", "saans ki takleef", "کھانسی", "سانس کی تکلیف", "smoking", "cigarette"],
        "reply_en": (
            "COPD (Chronic Obstructive Pulmonary Disease): Main symptoms are chronic cough (khansi) and breathing difficulty (saans ki takleef). "
            "CRITICAL: Quit smoking completely — this is the most important step. "
            "Treatment includes inhalers, steroids, or oxygen therapy as prescribed by a pulmonologist. "
            "Do NOT self-medicate. Find a pulmonologist on TeleCare."
        ),
        "reply_ur": (
            "COPD: Main symptoms ہیں کھانسی اور سانس کی تکلیف۔ "
            "اہم ترین قدم: Smoking فوراً بند کر دیں — یہ سب سے ضروری ہے۔ "
            "Treatment: Inhalers، steroids، oxygen therapy — Pulmonologist prescribe کرے گا۔ "
            "Self-medicate نہ کریں۔ TeleCare par pulmonologist dhoond sakte hain۔"
        ),
        "followUp_en": "Do you smoke or have a history of smoking? How long have you had these breathing symptoms?",
        "followUp_ur": "کیا آپ smoke کرتے ہیں یا smoking کا ہسٹری ہے؟ یہ symptoms کتنے دن سے ہیں؟",
        "condition": "copd"
    },
    {
        "keys": ["coronary artery", "heart disease", "seene mein dard", "سینے میں درد", "saans phoolna", "سانس پھولنا", "cardiac"],
        "reply_en": (
            "Coronary Artery Disease: Symptoms include chest pain (seene mein dard) and shortness of breath (saans phoolna). "
            "For emergency symptoms: go to hospital immediately or call 1122. "
            "Regular treatment: consult a cardiologist, take medicines (statins, aspirin, beta-blockers) as prescribed, follow healthy diet and exercise. "
            "Find a cardiologist on TeleCare."
        ),
        "reply_ur": (
            "Coronary Artery Disease: Symptoms ہیں سینے میں درد اور سانس پھولنا۔ "
            "Emergency symptoms پر: فوراً Hospital جائیں یا 1122 کال کریں۔ "
            "Regular treatment: Cardiologist consultation، medicines، healthy diet aur exercise۔ TeleCare par cardiologist dhoond sakte hain۔"
        ),
        "followUp_en": "Have you had any heart-related tests (ECG, stress test)? Are you currently on any heart medicine?",
        "followUp_ur": "کیا آپ نے Heart-related tests (ECG) کروائے ہیں؟ کیا آپ Heart کی دوائی لے رہے ہیں؟",
        "condition": "coronary_artery"
    },
    {
        "keys": ["chronic kidney", "ckd", "kidney disease", "soojan", "سوجن", "urine changes", "پیشاب", "kidney"],
        "reply_en": (
            "Chronic Kidney Disease (CKD): Symptoms include fatigue (thakan), swelling (soojan), and urine changes. "
            "Get kidney tests regularly (creatinine, urea). Consult a nephrologist. "
            "AVOID painkillers (NSAIDs like Brufen) without doctor's advice. "
            "Control BP and diabetes properly. Find a nephrologist on TeleCare."
        ),
        "reply_ur": (
            "Chronic Kidney Disease (CKD): symptoms ہیں تھکائی، سوجن اور پیشاب میں تبدیلی۔ "
            "Kidney tests باقایدہ کروائیں۔ Nephrologist سے مشورہ کریں۔ "
            "Painkillers (NSAIDs) بغیر ڈاکٹر کے استعمال نہ کریں۔ BP اور diabetes control کریں۔ TeleCare par nephrologist dhoond sakte hain۔"
        ),
        "followUp_en": "Have you had any kidney function tests recently? What was your creatinine level? Any swelling in your legs or face?",
        "followUp_ur": "کیا آپ نے حالیہ Kidney tests کروائے ہیں؟ آپ کا creatinine کتنا تھا؟ ٹانگ یا چہرے میں سوجن ہے؟",
        "condition": "ckd"
    },
    {
        "keys": ["arthritis", "joint pain", "knee pain", "gathiya", "گٹھیا", "جوڑ کا درد", "stiffness", "akat"],
        "reply_en": (
            "Arthritis: Joint pain (joron ka dard) and morning stiffness (subah akat) are common symptoms. "
            "Do mild exercise, apply hot compress, avoid heavy weights. "
            "Pain relief: paracetamol or ibuprofen may help temporarily — but consult an Orthopedic for long-term treatment including calcium and Vitamin D supplements. "
            "Find an Orthopedic on TeleCare."
        ),
        "reply_ur": (
            "Arthritis (گٹھیا): جوڑ کا درد اور صبح میں akat عام symptoms ہیں۔ "
            "Halka exercise، garam compress، bojh na uthayen۔ "
            "Pain relief: paracetamol ya ibuprofen — لیکن Orthopedic سے long-term treatment liye calcium aur Vitamin D supplements کے liye mashwara karen۔ TeleCare par orthopedic dhoond sakte hain۔"
        ),
        "followUp_en": "Which joint is most painful? Is there swelling? Any morning stiffness lasting more than 30 minutes?",
        "followUp_ur": "کون سا جوڑ زیادہ درد کرتا ہے؟ کسی سوجن کا ہونا ہے؟ صبح میں akat 30 منٹ سے زیادہ رہتی ہے؟",
        "condition": "arthritis"
    },
    {
        "keys": ["osteoporosis", "haddi kamzor", "ہڈی کمزور", "bone weak", "haddi", "ہڈی", "fracture", "break bone"],
        "reply_en": (
            "Osteoporosis: Weak bones (haddi kamzor) and fractures from minor falls are symptoms. "
            "Important: Take Calcium (dairy products, spinach) and Vitamin D (sunlight, supplements). "
            "Do weight-bearing exercises like walking. Get a bone density test (DEXA scan). "
            "Consult an Endocrinologist or Orthopedic. Find specialists on TeleCare."
        ),
        "reply_ur": (
            "Osteoporosis: ہڈی کمزور ہونا اور ہلکی گراوٹ سے fracture ہونا symptoms ہیں۔ "
            "مهم: Calcium (دودھ، پالک) اور Vitamin D (دھوپ، supplements) لینا۔ Walking exercise کریں۔ "
            "Bone density test (DEXA scan) کروائیں۔ Endocrinologist ya Orthopedic se consult karen۔ TeleCare par specialist dhoond sakte hain۔"
        ),
        "followUp_en": "Have you had any bone density tests? Do you have a history of fractures from minor falls?",
        "followUp_ur": "کیا آپ نے Bone density test کروائی ہے؟ کچھ ہلکی گراوٹ سے fracture ہوا ہے؟",
        "condition": "osteoporosis"
    },
    {
        "keys": ["hypothyroidism", "thyroid low", "wazan barhna", "وزن بڑھنا", "thyroid", "تھائرائڈ", "thakan", "ٹھیک", "cold intolerance"],
        "reply_en": (
            "Hypothyroidism (Thyroid Low): Symptoms include weight gain (wazan barhna), fatigue (thakan), and cold intolerance. "
            "Get thyroid tests (TSH, T3, T4). Medicine: Levothyroxine — but ONLY take after endocrinologist's prescription. "
            "Regular monitoring is essential. Find an endocrinologist on TeleCare."
        ),
        "reply_ur": (
            "Hypothyroidism (تھائرائڈ کم): Symptoms ہیں وزن بڑھنا، تھکائی اور ٹھنڈ سے زیادہ sensitivity۔ "
            "Thyroid tests (TSH، T3، T4) کروائیں۔ Medicine Levothyroxine — لیکن Endocrinologist کی prescription پر ہی لیں۔ "
            "Regular monitoring ضروری ہے۔ TeleCare par endocrinologist dhoond sakte hain۔"
        ),
        "followUp_en": "Have you had your thyroid levels checked recently? What was your TSH level?",
        "followUp_ur": "کیا آپ نے حالیہ Thyroid tests کروائے ہیں؟ آپ کا TSH کتنا تھا؟",
        "condition": "hypothyroidism"
    },
    {
        "keys": ["hyperthyroidism", "thyroid high", "dil tez", "دل تیز", "wazan kam", "وزن کم", "anxiety", "ghabrahat", "غب}ر"],
        "reply_en": (
            "Hyperthyroidism (Thyroid High): Symptoms include rapid heartbeat (dil tez dhadakna), weight loss (wazan kam hona), and anxiety. "
            "Consult an endocrinologist. Treatment options: medicines (methimazole), radioactive iodine, or surgery. "
            "Regular follow-up is essential. Find an endocrinologist on TeleCare."
        ),
        "reply_ur": (
            "Hyperthyroidism (تھائرائڈ زیادہ): Symptoms ہیں دل کی دھڑکن تیز، وزن کم ہونا اور anxiety۔ "
            "Endocrinologist سے مشورہ کریں۔ Treatment: medicines، radioactive iodine، ya surgery۔ "
            "Regular follow-up ضروری ہے۔ TeleCare par endocrinologist dhoond sakte hain۔"
        ),
        "followUp_en": "Have you noticed any recent weight changes? Any heart palpitations or increased anxiety?",
        "followUp_ur": "کیا آپ نے حالیہ میں وزن میں تبدیلی محسوس کی ہے؟ دل کی دھڑکن تیز ہے یا anxiety بڑھ گئی ہے؟",
        "condition": "hyperthyroidism"
    },
    {
        "keys": ["depression", "udaasi", "اداسی", "sadness", "interest khatam", "mental health", "ڈپریشن", "dil kiULDITY"],
        "reply_en": (
            "Depression: Symptoms include persistent sadness (udaasi), loss of interest (interest khatam hona), sleep/appetite changes, feelings of hopelessness. "
            "This is NOT weakness — it's a medical condition. Consult a mental health professional (psychiatrist/psychologist). "
            "Counseling, therapy (CBT), and medicines (antidepressants) help. Find mental health specialists on TeleCare."
        ),
        "reply_ur": (
            "ڈپریشن (Depression): Symptoms ہیں اداسی، Interest ختم ہونا، نیند/خوراک میں تبدیلی، ناامیدی کا احساس۔ "
            "یہ weakness نہیں، medical condition ہے۔ Mental health professional (psychiatrist/psychologist) سے ملیں۔ "
            "Counseling، therapy aur medicines help karte ہیں۔ TeleCare par mental health specialists dhoond sakte hain۔"
        ),
        "followUp_en": "How long have you been feeling this way? Any changes in sleep or appetite? Have you talked to anyone about this?",
        "followUp_ur": "آپ کتنی دیر سے یہ محسوس کر رہے ہیں؟ نیند یا خوراک میں کئی تبدیلیاں ہو گئی ہیں؟ کسی سے بات کر چکے ہیں؟",
        "condition": "depression"
    },
    {
        "keys": ["anxiety", "ghabrahat", "غب}ر", "bechaini", "panic", "ڈر", "stress", "dil tez dhadakna", "anxiety disorder"],
        "reply_en": (
            "Anxiety Disorder: Symptoms include excessive worry (ghabrahat), restlessness (bechaini), rapid heartbeat, and breathing problems. "
            "Helpful: Counseling, CBT (Cognitive Behavioral Therapy), deep breathing exercises, meditation, lifestyle changes. "
            "Medicines (anxiolytics) are also available but only with psychiatrist's prescription. Find mental health specialists on TeleCare."
        ),
        "reply_ur": (
            "Anxiety Disorder (گھبراہٹ): Symptoms ہیں زیادہ فکر، بے چینی، دل کی دھڑکن تیز اور سانس کی تکلیف۔ "
            "Helpful: Counseling، CBT، exercises، meditation aur lifestyle changes۔ "
            "Medicines ( anxiolytics)Psychiatrist کی prescription پر ہی استعمال کریں۔ TeleCare par mental health specialists dhoond sakte hain۔"
        ),
        "followUp_en": "How often do you feel anxious? Any specific situations that trigger it? Do you have panic attacks?",
        "followUp_ur": "آپ کتنی بار anxious محسوس کرتے ہیں؟ کوئی خاص situations ہیں jo trigger karti hain؟ آیا panic attacks ہوتے ہیں؟",
        "condition": "anxiety"
    },
    {
        "keys": ["epilepsy", "fits", "seizure", "dooon", "ڈر", "convulsion", "خواب", "behoshi", " seizure disorder"],
        "reply_en": (
            "Epilepsy: Main symptom is seizures/fits (behoshi ke attack). "
            "CRITICAL: Take prescribed anti-seizure medicines REGULARLY — never skip doses. "
            "Avoid triggers: lack of sleep, flashing lights, alcohol. "
            "Emergency: If seizure lasts more than 5 minutes, call 1122 immediately. "
            "Regular follow-up with a neurologist is essential. Find one on TeleCare."
        ),
        "reply_ur": (
            "Epilepsy (اپلپسی): Main symptom seizures/fits ہوتے ہیں (behoshi کا حملہ)۔ "
            "اہم: Anti-seizure medicines باقاعدہ لینا — dose skip نہ کریں۔ "
            "Triggers avoid کریں: نیند کم، flashing lights، alcohol۔ "
            "Emergency: اگر seizure 5 منٹ سے زیادہ چلا تو 1122 کال کریں۔ Neurologist سے باقاعده follow-up ضروری ہے۔ TeleCare par neurologist dhoond sakte hain۔"
        ),
        "followUp_en": "How often do you have seizures? Are you currently taking anti-seizure medicine? Any recent changes in seizure pattern?",
        "followUp_ur": "آپ کو کتنی بار seizures ہوتے ہیں؟ کیا آپ anti-seizure medicine لے رہے ہیں؟ Recent changes محسوس ہوئے؟",
        "condition": "epilepsy"
    },
    {
        "keys": ["parkinson", "hath ka kampna", "ہاتھ کا کمپن", "tremor", "kampna", "muscle stiffness", "ہاتھ", "pagal ki bimari"],
        "reply_en": (
            "Parkinson's Disease: Symptoms include hand tremors (hath ka kampna), muscle stiffness, and slow movement. "
            "Regular follow-up with a neurologist is essential. "
            "Medicines (Levodopa, dopamine agonists) must be taken regularly. "
            "Physical therapy and exercise also help. Find a neurologist on TeleCare."
        ),
        "reply_ur": (
            "Parkinson's Disease: Symptoms ہیں ہاتھ کا کمپن، muscle stiffness اور حرکت میں slowness۔ "
            "Neurologist سے باقاعده follow-up ضروری ہے۔ "
            "Medicines (Levodopa) باقایدہ لینا۔ Physical therapy aur exercise help karta ہے۔ TeleCare par neurologist dhoond sakte hain۔"
        ),
        "followUp_en": "How long have you had these symptoms? Are you currently on any Parkinson's medication?",
        "followUp_ur": "آپ کو یہ symptoms کتنے دن سے ہیں؟ کیا آپ Parkinson's medicine لے رہے ہیں؟",
        "condition": "parkinsons"
    },
    {
        "keys": ["alzheimer", "yaad dasht", "یاد داشت", "memory loss", "memory weak", "bhoolna", "dementia", "دم magnetic", " Dementia"],
        "reply_en": (
            "Alzheimer's Disease: Symptoms include memory loss (yaad-dasht kamzor hona), confusion, and personality changes. "
            "Specialized care and regular monitoring are essential. "
            "Medicines (cholinesterase inhibitors) may slow progression. "
            "Support groups and family care are important. Find a neurologist/psychiatrist on TeleCare."
        ),
        "reply_ur": (
            "Alzheimer's Disease: Symptoms ہیں یاد داشت کمزور ہونا، confused ہونا، personality changes۔ "
            "Specialized care aur monitoring ضروری ہے۔ "
            "Medicines progression کو slow kar sakte ہیں۔ Support groups aur family care important ہے۔ TeleCare par neurologist/psychiatrist dhoond sakte hain۔"
        ),
        "followUp_en": "How long have you noticed memory problems? Is the person aware of their memory loss? Any difficulty with daily tasks?",
        "followUp_ur": "آپ کو یاد داشت کے problems کتنے دن سے محسوس ہو رہے ہیں؟ کیا person apni bhool یاد رکھتا ہے؟ Daily tasks میں دشواری؟",
        "condition": "alzheimers"
    },
    {
        "keys": ["cancer", "tumor", "oncology", "کینسر", "کینسر", "massa", "growth", "unusual lump", "cancer treatment"],
        "reply_en": (
            "Cancer: Symptoms vary by type (weight loss, unusual lump, persistent bleeding, chronic cough). "
            "IMPORTANT: Early detection is crucial. Get screening tests (mammogram, colonoscopy, PSA) as recommended. "
            "Treatment options: surgery, chemotherapy, radiation — decided by oncologist. "
            "Do NOT ignore persistent symptoms — see a doctor immediately. Find an oncologist on TeleCare."
        ),
        "reply_ur": (
            "Cancer (کینسر): Symptoms type ke hisaab se different ہوتے ہیں (weight loss، lump، bleeding، cough)۔ "
            "اہم: Early detection Zaroori ہے۔ Screening tests کروائیں۔ "
            "Treatment: surgery، chemotherapy، radiation — Oncologist decide karega۔ "
            "Persistent symptoms ignore نہ کریں۔ TeleCare par oncologist dhoond sakte hain۔"
        ),
        "followUp_en": "What type of cancer are you concerned about? Have you had any screening tests recently? Any persistent symptom for more than 2 weeks?",
        "followUp_ur": "آپ کون سا type cancer consultation کرنا چاہتے ہیں؟ کیا آپ نے screening tests کروائے ہیں؟ کئی symptoms 2 ہفتے سے زیادہ ہو رہا ہے؟",
        "condition": "cancer"
    },
    {
        "keys": ["hepatitis b", "hepatitis-b", "jigar", "جگر", "liver b", "jaundice", "یرو", "hepatitis b symptoms"],
        "reply_en": (
            "Hepatitis B: Liver infection (jigar ke masail). Symptoms: weakness, yellow eyes/skin (jaundice), abdominal pain. "
            "Get blood tests (HBsAg, liver function). Liver specialist (hepatologist) consultation is essential. "
            "Vaccination is available for prevention. Avoid alcohol completely. Find a hepatologist on TeleCare."
        ),
        "reply_ur": (
            "Hepatitis B: Jigar ki infection۔ Symptoms: تھکائی، yellow eyes/skin، abdominal pain۔ "
            "Blood tests (HBsAg، liver function) کروائیں۔ Hepatologist consultation ضروری ہے۔ "
            "Vaccination prevention ke liye available ہے۔ Alcohol completely avoid کریں۔ TeleCare par hepatologist dhoond sakte hain۔"
        ),
        "followUp_en": "Have you had your Hepatitis B status checked? Any yellowing of eyes or skin recently?",
        "followUp_ur": "کیا آپ نے Hep B status check کروائی ہے؟ Recent mein eyes ya skin yellow ہوئی ہے؟",
        "condition": "hepatitis_b"
    },
    {
        "keys": ["hepatitis c", "hepatitis-c", "liver c", "jigar c", "liver damage", "jigar nuqsaan", "HCV", "hepatitis c symptoms"],
        "reply_en": (
            "Hepatitis C: Liver infection. Often NO symptoms for years (asymptomatic). "
            "Symptoms when present: fatigue, liver damage, abdominal discomfort. "
            "Blood test (Anti-HCV) is essential. Treatment: antiviral medicines (sofosbuvir/ledipasvir) available — consult hepatologist. "
            "Regular monitoring is crucial. Avoid alcohol completely. Find a hepatologist on TeleCare."
        ),
        "reply_ur": (
            "Hepatitis C: Jigar ki infection۔ Years tak symptoms nahi hotay (asymptomatic)۔ "
            "Symptoms: تھکائی، liver damage، abdominal discomfort۔ "
            "Blood test (Anti-HCV) Zaroori ہے۔ Treatment: antiviral medicines —— Hepatologist se consult karen۔ "
            "Regular monitoring crucial ہے۔ Alcohol avoid کریں۔ TeleCare par hepatologist dhoond sakte hain۔"
        ),
        "followUp_en": "Have you ever been tested for Hepatitis C? Any history of blood transfusion or IV drug use?",
        "followUp_ur": "کیا آپ نے Hep C test کروایا ہے؟ Blood transfusion ya IV drug use کا ہسٹری ہے؟",
        "condition": "hepatitis_c"
    },
    {
        "keys": ["ibd", "inflammatory bowel", "crohn", "ulcerative colitis", "pet dard", "پت درد", "diarrhea", "dastana", "pet kharab", "گistro"],
        "reply_en": (
            "Inflammatory Bowel Disease (IBD): Symptoms include abdominal pain (pet dard), diarrhea (can be bloody), weight loss, fatigue. "
            "Consult a gastroenterologist. Treatment: medicines (aminosalicylates, steroids, biologics) or surgery. "
            "Diet modifications help: avoid spicy/processed food, eat small frequent meals. "
            "Regular follow-up is essential. Find a gastroenterologist on TeleCare."
        ),
        "reply_ur": (
            "IBD (inflammatory Bowel Disease): Symptoms ہیں پت درد، diarrhea (خون والا bhi ho sakta hai)، weight loss، تھکائی۔ "
            "Gastroenterologist se consult karen۔ Treatment: medicines ya surgery۔ "
            "Diet modifications: spicy/processed food avoid کریں۔ TeleCare par gastroenterologist dhoond sakte hain۔"
        ),
        "followUp_en": "How long have you had these digestive symptoms? Any blood in your stool? Recent weight loss?",
        "followUp_ur": "آپ کو یہ digestive symptoms کتنے دن سے ہیں؟ Stool mein خون آیا ہے؟ Recent weight loss؟",
        "condition": "ibd"
    },
    {
        "keys": ["migraine", "migrane", "شدید سر درد", "severe headache", "headache one side", "سر درد ایک طرف", "migraine symptoms"],
        "reply_en": (
            "Migraine: Severe headache (usually one-sided), nausea, and sensitivity to light/sound. "
            "Avoid triggers: stress, certain foods (cheese, chocolate), irregular sleep, bright lights. "
            "OTC: paracetamol or ibuprofen in early stage — but consult a neurologist for proper treatment. "
            "Preventive medicines are also available. Find a neurologist on TeleCare."
        ),
        "reply_ur": (
            "Migraine (شدید سر درد ایک طرف): Symptoms ہیں شدید سر درد، nausea اور light/sound سے sensitivity۔ "
            "Triggers avoid کریں: stress، certain foods، نیند میں عدم استقلال۔ "
            "OTC: paracetamol ya ibuprofen early stage mein — لیکن Neurologist se proper treatment lein۔ Preventive medicines bhi available ہیں۔ TeleCare par neurologist dhoond sakte hain۔"
        ),
        "followUp_en": "How often do you get migraines? Do you have any known triggers (food, stress, sleep pattern)?",
        "followUp_ur": "آپ کو migraine کتنی بار ہوتا ہے؟ کیا آپ کو triggers معلوم ہیں (خوراک، stress، نیند)؟",
        "condition": "migraine"
    },
]

GENERAL_TIPS = [
    (
        ["diet", "immunity", "nutrition", "eat"],
        "Eat balanced meals: vegetables, protein, whole grains, and plenty of water. Sleep 7-8 hours.",
    ),
    (
        ["sleep", "insomnia"],
        "Keep a regular sleep schedule, avoid screens before bed, limit caffeine.",
    ),
    (
        ["exercise", "weight", "fitness"],
        "Aim for 30 minutes of moderate activity most days. For weight concerns, ask a doctor on TeleCare.",
    ),
]


def detect_language(text):
    urdu_chars = len(re.findall(r'[\u0600-\u06FF]', text or ''))
    return 'urdu' if urdu_chars > 3 else 'english'


def normalize(text):
    return re.sub(r"\s+", " ", (text or "").lower().strip())


def matches(msg, keys):
    compact = msg.replace(" ", "")
    for k in keys:
        kc = k.replace(" ", "")
        if k in msg or kc in compact:
            return True
    return False


def get_reply(message):
    msg = normalize(message)
    if not msg:
        return "Please type your health concern or symptom.", None, "general"

    lang = detect_language(message)

    if matches(msg, EMERGENCY_KEYS):
        en = EMERGENCY_REPLY
        ur = "یہ طبی ہنگامی صورت حال ہو سکتی ہے۔ فوراً 1122 کال کریں اور ہسپتال جائیں۔ کوئی دوائی خود بخود نہ لیں۔"
        return (ur if lang == 'urdu' else en), (ur if lang == 'urdu' else "Are you experiencing difficulty breathing right now? Is your face drooping on one side?"), "emergency"

    for rule in SYMPTOM_RULES:
        if matches(msg, rule["keys"]):
            return (
                rule["reply_ur"] if lang == 'urdu' else rule["reply_en"],
                rule["followUp_ur"] if lang == 'urdu' else rule["followUp_en"],
                rule["condition"]
            )

    for keys, reply in GENERAL_TIPS:
        if matches(msg, keys):
            return reply, None, "general"

    if "book" in msg or "appointment" in msg:
        en = (
            "To book: choose your city under Appointment Booking on the home page, pick a doctor, "
            "view profile, then Book appointment. Or use Find doctor to search by symptom."
        )
        ur = (
            "رجسٹریشن کے لیے: Appointment Booking میں اپنا شہر منتخب کریں، ڈاکٹر منتخب کریں، "
            "پروفائل دیکھیں اور Book appointment کلک کریں۔ یا Find doctor سے تلاش کریں۔"
        )
        return (ur if lang == 'urdu' else en), None, "booking"

    if "hello" in msg or "hi" in msg or "salam" in msg or "assalam" in msg:
        en = (
            "Assalam o Alaikum! I'm TeleCare's health assistant. Tell me your symptoms and I'll suggest the right specialist. "
            "For emergencies, type 'emergency' or call 1122."
        )
        ur = (
            "السلام عليكم! میں ٹیلی کیئر کی ہیلتھ اسسٹنٹ ہوں۔ اپنی علامات بتائیں، میں آپ کو درست سپیشلسٹ تجویز کروں گا۔ "
            "ہنگامی صورتحال میں 1122 کال کریں۔"
        )
        return (ur if lang == 'urdu' else en), (ur if lang == 'urdu' else "What symptoms or health concern do you have today?"), "greeting"

    en = (
        "I can help with symptoms and which doctor to see on TeleCare. "
        "Try describing your issue (e.g. headache, chest pain, fever). "
        "For emergencies call 1122. Use Find doctor or Appointment Booking on this page."
    )
    ur = (
        "میں علامات اور کون سا ڈاکٹر دیکھنا ہو اس میں مدد کر سکتا ہوں۔ اپنے مسئلے کی وضاحت کریں۔ "
        "ہنگامی صورتحال میں 1122 کال کریں۔ ڈاکٹر تلاش یا Appointment Booking استعمال کریں۔"
    )
    return (ur if lang == 'urdu' else en), (ur if lang == 'urdu' else "Please describe your symptoms in detail so I can suggest the right doctor."), "general"


def main():
    raw = sys.stdin.read()
    try:
        payload = json.loads(raw) if raw.strip() else {}
    except json.JSONDecodeError:
        payload = {"message": raw}
    message = payload.get("message", "")
    
    reply, followUp, condition = get_reply(message)
    
    result = {"reply": reply}
    if followUp:
        result["followUp"] = followUp
    result["condition"] = condition
    result["language"] = detect_language(message)
    
    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
