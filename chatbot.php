<?php
// ================================================
// chatbot.php — TeleCare Health Assistant (Full Chronic Disease Support)
// ================================================

session_start();
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Credentials: true");

$input = json_decode(file_get_contents('php://input'), true);
$user_message = trim($input['message'] ?? '');

if ($user_message === '') {
    echo json_encode(["reply" => "Please type your health concern."]);
    exit;
}

/**
 * Call Python chatbot (trained rules).
 */
function callPythonChatbot(string $message): ?array
{
    $script = __DIR__ . DIRECTORY_SEPARATOR . 'chatbot.py';
    if (!is_file($script)) {
        return null;
    }

    $payload = json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    $candidates = ['python', 'py', 'python3'];

    foreach ($candidates as $py) {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cmd = escapeshellarg($py) . ' ' . escapeshellarg($script);
        $proc = @proc_open($cmd, $descriptors, $pipes, __DIR__);
        if (!is_resource($proc)) {
            continue;
        }
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        $data = json_decode($out ?? '', true);
        if (!empty($data['reply'])) {
            return $data;
        }
    }
    return null;
}

/**
 * PHP fallback — comprehensive chronic disease rules with follow-up questions.
 */
function localChatbotReply(string $message): array
{
    $msg = strtolower(preg_replace('/\s+/', ' ', trim($message)));
    $compact = str_replace(' ', '', $msg);

    $has = function (array $keys) use ($msg, $compact): bool {
        foreach ($keys as $k) {
            $kc = str_replace(' ', '', $k);
            if (strpos($msg, $k) !== false || strpos($compact, $kc) !== false) {
                return true;
            }
        }
        return false;
    };

    $urdu = function (string $text): bool {
        $urdu_chars = preg_match_all('/[\u0600-\u06FF]/', $text);
        return $urdu_chars > 0;
    };

    $is_urdu_msg = $urdu($message);

    // EMERGENCY WARNING SIGNS
    $emergency = [
        'emergency', 'urgent', '1122', '115', 'ambulance', 'heart attack', 'heartattack',
        "can't breathe", 'cant breathe', 'unconscious', 'severe bleeding', 'stroke', 'choking', 'critical',
        'ہنگامی', 'فوری', 'ایمرجنسی', 'ایمبولینس', 'دل کا دورہ', 'دماغ کا دورہ',
        'بے ہوش', 'تشویش', 'سانس نہیں آ رہا', 'چہرہ ٹیڈھا', 'بات نہ کر پانا',
        'بہت زیادہ خون بہنا', 'گھبراہٹ', 'سخت الرجک'
    ];
    if ($has($emergency)) {
        return [
            'reply' => $is_urdu_msg 
                ? 'یہ طبی ہنگامی صورت حال ہو سکتی ہے۔ براہ کرم فوراً 1122 (Rescue) یا 115 (Edhi Ambulance) کال کریں اور قریب ترین ہسپتال جائیں۔ کوئی دوائی خود بخود نہ لیں۔'
                : 'This may be a medical emergency. Please seek immediate medical care or contact emergency services. Do NOT wait for an online appointment.',
            'followUp' => $is_urdu_msg ? 'کیا آپ کو اسی وقت سانس لینے میں دشواری محسوس ہو رہی ہے؟ یا چہرہ کسی طرف ٹیڈھا ہے؟' : 'Are you experiencing difficulty breathing right now? Is your face drooping on one side?',
            'condition' => 'emergency'
        ];
    }

    $rules = [
        // TYPE 2 DIABETES
        [['diabetes', 'diabetic', 'blood sugar', 'sugar disease', 'ذیابیطس', 'شوگر', 'بلڈ شوگر', 'پیا لگنا', 'پیشاب', 'थک'],
            'Type 2 Diabetes: Zyada pyaas, bar bar peshab, thakan symptoms hain. Blood sugar check karain, healthy diet follow karain (meetha avoid), rozana walk karen. Common medicines: Metformin ya Insulin — lekin ALWAYS diabetologist se consult karain. TeleCare par "diabetes" search karen apne shahar mein specialist dhoondne ke liye. | ذیابیطس: پیا لگنا، بار بار پیشاب، تھکائی علامات ہیں۔ بلڈ شوگر چیک کریں، میٹی فوڈ میٹ形象崇拜 ازدواجی، روزانہ واک کریں۔ ڈائی بیٹس اسpeciliست سے مشورہ کریں۔',
            'How long have you had diabetes? Are you currently taking any medicine? What is your latest blood sugar reading (fasting/after meal)?',
            'ذیابیطس کتنے سال سے ہے؟ کیا آپ کوئی دوائی لے رہے ہیں؟ آپ کا پہلے/م吃过 کا بلڈ شوگر کتنا ہے؟',
            'diabetes'],

        // HYPERTENSION / HIGH BP
        [['bp', 'blood pressure', 'high pressure', 'low pressure', 'hypertension', 'بلڈ پریشر', 'ہائی پریشر', 'لوی پریشر', 'sar dard', 'chakkar', 'سر درد', 'چکر'],
            'Hypertension (High BP): Sar dard aur chakkar symptoms ho sakte hain, lekin kuch patients ke paas koi symptoms nahi hote. BP monitor karain regularly, namak/盐 kam karen, stress avoid karen, walk karen. Common medicines: Amlodipine ya Telmisartan — lekin ALWAYS cardiologist ya physician se consult karain. TeleCare par "bp" search karen. | ہائی بلڈ پریشر: سر درد اور چکر ہو سکتے ہیں۔ BP چیک کریں، نمک کم کریں، کلavidین یا Telmisartan — لیکن ڈاکٹر سے مشورہ ضروری۔',
            'How long have you had high BP? Are you on any BP medicine currently? What is your latest BP reading?',
            'ہائی بلڈ پریشر کتنے سال سے ہے؟ کیا آپ بلڈ پریشر کی دوائی لے رہے ہیں؟ آپ کا آخری پریشر کتنا تھا؟',
            'hypertension'],

        // ASTHMA
        [['asthma', 'breathing problem', 'difficulty breathing', 'دمہ', 'سانس کی تکلیف', 'sans ka phoolna', 'wheezing', 'سانس کا Fulna'],
            'Asthma: Sans ka phoolna, wheezing (سانس میں س光明寺 sound) symptoms hain. Inhaler use karain (Salbutamol/Ventolin), dust, smoke aur cold air se bachain. ALWAYS pulmonologist se prescription le kar hi inhaler use karen. TeleCare par "asthma" search karen long-term management plan ke liye. | دمہ: سانس کا Fulna، wheezingSymptoms ہیں۔ Inhaler استعمال کریں، دھول اور smoke سے بچیں۔ پلمونالاجسٹ سے مشورہ کریں۔',
            'How long have you had asthma? Do you use an inhaler regularly? Any recent attacks or ER visits?',
            'دمہ کتنے سال سے ہے؟ کیا آپ Inhaler باقاعدہ استعمال کرتے ہیں؟ حالیہ کئی حملے ہوئے ہیں یا ER آیا؟',
            'asthma'],

        // COPD
        [['copd', 'chronic obstructive', 'khansi', 'saans ki takleef', 'کھانسی', 'سانس کی تکلیف', 'smoking'],
            'COPD (Chronic Obstructive Pulmonary Disease): Khansi aur saans ki takleef main symptoms hain. Smoking band karain ZAROORI hai, apne aap koi medicine nahi leni. Doctor se treatment plan lein — may include inhalers, steroids, ya oxygen therapy. TeleCare par pulmonologist dhoond sakte hain. | COPD: کھانسی اور سانس کی تکلیف۔ Smoking فوراً بند کر دیں۔ ڈاکٹر سے ٹریٹمنٹ پلان لیں۔ پلمونالاجسٹ سے مشورہ کریں۔',
            'Do you smoke or have a history of smoking? How long have you had these breathing symptoms?',
            'کیا آپ smoke کرتے ہیں یا smoking کا ہسٹری ہے؟ یہ symptoms کتنے دن سے ہیں؟',
            'copd'],

        // CORONARY ARTERY DISEASE
        [['coronary', 'artery', 'heart disease', 'seene mein dard', 'سینے میں درد', 'saans phoolna', 'سانس پھولنا'],
            'Coronary Artery Disease: Seene mein dard aur saans phoolna symptoms hain. Emergency symptoms par foran hospital jayen — 1122 karen. Regular treatment: cardiologist se consult, medicines (statins, aspirin, beta-blockers) lein, healthy diet aur exercise follow karain. TeleCare par cardiologist dhoond sakte hain. | کارونییری آرٹری ڈیزیز: سینے میں درد اور سانس پھولنا۔ Emergency meinHospital جائیں۔ کارڈیالوجسٹ سے مشورہ کریں۔',
            'Have you had any heart-related tests (ECG, stress test)? Are you currently on any heart medicine?',
            'کیا آپ نے کئی ہارٹ ٹیسٹ (ECG) کروائے ہیں؟ کیا آپ Heart کی دوائی لے رہے ہیں؟',
            'coronary_artery'],

        // CHRONIC KIDNEY DISEASE
        [['kidney', 'chronic kidney', 'ckd', 'soojan', 'سوجن', 'urine changes', 'پیشاب میں کمی', 'پیشاب کی رنگت'],
            'Chronic Kidney Disease (CKD): Thakan, soojan, urine changes (کم یا زیادہ پISHAB، رنگت بدلنا) symptoms hain. Kidney tests (creatinine, urea) karain regularly. Nephrologist se consult karain. Avoid painkillers (NSAIDs) without doctor advice. Control BP aur diabetes properly. TeleCare par nephrologist dhoond sakte hain. | CKD: تھکائی، سوجن، پیشاب بدلنا۔ Kidney tests کرتے رہیں۔ Nephrologist سے مشورہ کریں۔ painkillers بغیر ڈاکٹر کے استعمال نہ کریں۔',
            'Have you had any kidney function tests recently? What was your creatinine level? Any swelling in your legs or face?',
            'کیا آپ نے حالیہ Kidney function tests کروائے ہیں؟ آپ کا creatinine کتنا تھا؟ کسی ٹانگ یا چہرے میں سوجن ہے؟',
            'ckd'],

        // ARTHRITIS
        [['arthritis', 'joint pain', 'knee pain', 'gathiya', 'گٹھیا', 'جوڑ کا درد', 'stiffness', 'akath'],
            'Arthritis: Joron ka dard aur stiffness (صبح میں akath محسوس ہونا) common symptoms hain. Mild exercise, hot compress, avoid heavy weight. Pain relief: paracetamol ya ibuprofen temporarily help kar sakte hain — lekin Orthopedic se long-term treatment lein including calcium/vitamin D supplements. TeleCare par orthopedic dhoond sakte hain. | گٹھیا: جوڑ کا درد اور صبح میں akath۔ Exercise، compress، Orthopedic سے مشاورت۔',
            'Which joint is most painful? Is there swelling? Any morning stiffness that lasts more than 30 minutes?',
            'کون سا جوڑ زیادہ درد کرتا ہے؟ کسی سوجن کا ہونا ہے؟ صبح میں akat 30 منٹ سے زیادہ رہتی ہے؟',
            'arthritis'],

        // OSTEOPOROSIS
        [['osteoporosis', 'haddi kamzor', 'ہڈی کمزور', 'bone weak', 'fracture', 'ہڈی'],
            'Osteoporosis: Haddi kamzor hona aur fractures bina بڑے injury ke ho jana symptoms hain. Calcium (Dairy products, spinach) aur Vitamin D (suni exposure, supplements) zaroori hai. Weight-bearing exercise (walking) help karta hai. Endocrinologist ya Orthopedic se consult karain. Bone density test (DEXA scan) karain. TeleCare par specialist dhoond sakte hain. | Osteoporosis: ہڈی کمزور، fractures بina cause۔ Calcium aur Vitamin D، Exercise۔',
            'Have you had any bone density tests? Do you have a history of fractures from minor falls?',
            'کیا آپ نے Bone density test کروائی ہے؟ کچھ ہلکی گراوٹ سے fracture ہوا ہے؟',
            'osteoporosis'],

        // HYPOTHYROIDISM
        [['hypothyroidism', 'thyroid low', 'wazan barhna', 'وزن بڑھنا', 'thyroid', 'تھائرائڈ', 'thakan', 'ٹھیک'],
            'Hypothyroidism (Thyroid Low): Wazan barhna, thakan, cold intolerance symptoms hain. Thyroid tests (TSH, T3, T4) karain. Medicine: Levothyroxine — lekin ALWAYS endocrinologist se prescription ke baad hi leni. Regular monitoring zaroori hai. TeleCare par endocrinologist dhoond sakte hain. | Hypothyroidism: وزن بڑھنا، تھکائی۔ Thyroid tests، Levothyroxine medicine — لیکن Endocrineologist سے مشورہ ضروری۔',
            'Have you had your thyroid levels checked recently? What was your TSH level?',
            'کیا آپ نے حالیہ Thyroid tests کروائے ہیں؟ آپ کا TSH کتنا تھا؟',
            'hypothyroidism'],

        // HYPERTHYROIDISM
        [['hyperthyroidism', 'thyroid high', 'dil tez', 'دل تیز', 'wazan kam hona', 'وزن کم ہونا', ' anxiety'],
            'Hyperthyroidism (Thyroid High): Dil tez dhadakna, wazan kam hona, ghabrahat, sensitivity to heat symptoms hain. Endocrinologist se consult karain. Treatment: medicines (methimazole), radioactive iodine, ya surgery. Regular follow-up zaroori hai. | Hyperthyroidism: دل تیز دھڑکنا، وزن کم ہونا۔ Endocrinologist سے مشورہ کریں، treatment follow karین۔',
            'Have you noticed any changes in your weight recently? Any heart palpitations or anxiety?',
            'کیا آپ نے حالیہ میں وزن میں کمی محسوس کی ہے؟ دل کی دھڑکن تیز ہے یا anxiety ہے؟',
            'hyperthyroidism'],

        // DEPRESSION
        [['depression', 'udaasi', 'اداسی', 'sadness', 'interest khatam', 'دل کیULDITY', 'mental health'],
            'Depression: Udaasi, interest khatam hona, sleep/appetite changes, feelings of hopelessness symptoms hain. Mental health professional (psychiatrist/psychologist) se baat karain — yeh koi weakness nahi hai. Counseling aur therapy help karta hai. Medicines (antidepressants) bhi hotay hain lekin sirf doctor ke prescription par. TeleCare par mental health specialists dhoond sakte hain. | ڈپریشن: اداسی، Interest ختم ہونا۔ Mental health professional سے ملیں۔ Counseling aur therapy help karta hai۔',
            'How long have you been feeling this way? Any changes in sleep or appetite? Have you talked to anyone about this?',
            'آپ کتنی دیر سے یہ محسوس کر رہے ہیں؟ نیند یا خوراک میں کئی تبدیلیاں ہو گئی ہیں؟ کسی سے بات کر چکے ہیں؟',
            'depression'],

        // ANXIETY DISORDER
        [['anxiety', 'ghabrahat', 'غب}', 'bechaini', 'panic', 'ڈر', 'stress', 'dil tez dhadakna'],
            'Anxiety Disorder: Ghabrahat, bechaini, dil tez dhadakna, breathing problems symptoms hain. Counseling aur CBT help karta hai. Deep breathing exercises, meditation, aur lifestyle changes follow karain. Medicines (anxiolytics) bhi hotay hain lekin psychiatrist se consult zaroori hai. TeleCare par mental health specialists available hain. | Anxiety: گھبراہٹ، بے چینی، دل کی دھڑکن۔ Counseling، exercises aur lifestyle changes۔ Psychiatrist سے مشورہ کریں۔',
            'How often do you feel anxious? Any specific situations that trigger it? Do you have panic attacks?',
            'آپ کتنی بار anxious محسوس کرتے ہیں؟ کوئی خاص situations ہیں jo trigger karti hain؟ آیا panic attacks ہوتے ہیں؟',
            'anxiety'],

        // EPILEPSY
        [['epilepsy', 'fits', 'seizure', 'دوائیاں', 'ڈر', 'convulsion', 'خواب', 'behoshi'],
            'Epilepsy: Fits/seizures happening symptoms hain. Prescribed medicines (anticonvulsants) REGULARLY lain — kabhi medicine nahi chhodni. Avoid triggers: lack of sleep, flashing lights, alcohol. Neurologist se regular follow-up zaroori hai. Emergency: agar seizure 5 min se zyada chalta hai to 1122 karen. TeleCare par neurologist dhoond sakte hain. | Epilepsy: Fits/seizures۔ Prescribed medicines باقاعدہ لینا۔ Neurologist سے regular follow-up۔',
            'How often do you have seizures? Are you currently taking anti-seizure medicine? Any recent changes in seizure pattern?',
            'آپ کو کتنی بار seizures ہوتے ہیں؟ کیا آپ anti-seizure medicine لے رہے ہیں؟ recent changes؟',
            'epilepsy'],

        // PARKINSON'S DISEASE
        [['parkinson', 'hath ka kampna', 'ہاتھ کا کمپن', 'tremor', 'kampna', 'muscle stiffness', 'ہاتھ'],
            'Parkinson\'s Disease: Hath ka kampna (tremor), muscle stiffness, slow movement symptoms hain. Neurologist se regular follow-up zaroori hai. Medicines (Levodopa, dopamine agonists) regularly leni. Physical therapy aur exercise bhi help karta hai. TeleCare par neurologist dhoond sakte hain. | Parkinson\'s: ہاتھ کا کمپن، muscle stiffness۔ Neurologist سے регуляр follow-up۔ medicines регуляр لینا۔',
            'How long have you had these symptoms? Are you currently on any Parkinson\'s medication?',
            'آپ کو یہ symptoms کتنے دن سے ہیں؟ کیا آپ Parkinson\'s medicine لے رہے ہیں؟',
            'parkinsons'],

        // ALZHEIMER'S DISEASE
        [['alzheimer', 'yaad dasht', 'یاد داشت', 'memory loss', 'memory weak', 'bhoolna', ' Dementia', 'دمagnetic'],
            'Alzheimer\'s Disease: Yaad-dasht kamzor hona, confusion, personality changes symptoms hain. Specialized care aur monitoring zaroori hai. Neurologist/psychiatrist se consult karain. Support groups aur family care important hai. Medicines (cholinesterase inhibitors) slow progression kar sakte hain. | Alzheimer\'s: یاد داشت کمزور، hearings، personality changes۔ Specialist care aur monitoring۔',
            'How long have you noticed memory problems? Is the person aware of their memory loss? Any difficulty with daily tasks?',
            'آپ کو یاد داشت کے problems کتنے دن سے محسوس ہو رہے ہیں؟ کیا person apni bhool یاد رکھتا ہے؟ Daily tasks میں دشواری؟',
            'alzheimers'],

        // CANCER
        [['cancer', 'tumor', 'oncology', 'کینسر', 'کینسر', ' massa', 'growth', 'unusual lump'],
            'Cancer: Mukhtalif symptoms hain depending on type (weight loss, unusual lump, bleeding, persistent cough). IMPORTANT: Early detection zaroori hai. Screening tests (mammogram, colonoscopy, PSA) karain. Treatment: surgery, chemotherapy, radiation — oncologist decide karega. TeleCare par oncologist dhoond sakte hain. Do NOT ignore persistent symptoms — see a doctor immediately. | کینسر: مختلف symptoms۔ Early detection zaroorی۔ Screening tests۔oncologist consultant۔',
            'What type of cancer are you concerned about? Have you had any screening tests recently? Any persistent symptom for more than 2 weeks?',
            'آپ کنسrn کون سا type consultation کرنا چاہتے ہیں؟ کیا آپ نے screening tests کروائے ہیں؟ کئی symptoms 2 ہفتے سے زیادہ ہو رہا ہے؟',
            'cancer'],

        // HEPATITIS B
        [['hepatitis b', 'hepatitis-b', 'jigar', 'جگر', 'liver b', 'jaundice', 'یرو'],
            'Hepatitis B: Jigar ke masail, weakness, yellow eyes/skin (jaundice), abdominal pain symptoms hain. Liver specialist (hepatologist) se consult zaroori hai. Blood tests (HBsAg, liver function) karain. Vaccination available hai prevention ke liye. Avoid alcohol completely. TeleCare par hepatologist dhoond sakte hain. | Hep B: Jigar ke masail، yellow skin۔ Hepatologist se consultation۔ Blood tests۔',
            'Have you had your Hepatitis B status checked? Any yellowing of eyes or skin recently?',
            'کیا آپ نے Hep B status check کروائی ہے؟ Recent mein eyes ya skin yellow ہوئی ہے؟',
            'hepatitis_b'],

        // HEPATITIS C
        [['hepatitis c', 'hepatitis-c', 'liver c', 'jigar c', 'liver damage', 'jigar nuqsaan', 'HCV'],
            'Hepatitis C: Thakan, liver damage, abdominal discomfort symptoms hain. Often asymptomatic (no symptoms) for years. Blood test (Anti-HCV) zaroori hai. Treatment: antiviral medicines (sofosbuvir/ledipasvir) available hain — hepatologist se consult karain. Regular monitoring zaroori. Avoid alcohol completely. TeleCare par hepatologist dhoond sakte hain. | Hep C: تھکائی، liver damage۔ Blood test zaroori۔ Treatment available۔ Hepatologist se consult۔',
            'Have you ever been tested for Hepatitis C? Any history of blood transfusion or IV drug use?',
            'کیا آپ نے Hep C test کروایا ہے؟ Blood transfusion ya IV drug use کا ہسٹری ہے؟',
            'hepatitis_c'],

        // INFLAMMATORY BOWEL DISEASE (IBD)
        [['ibd', 'inflammatory bowel', 'crohn', 'ulcerative colitis', 'pet dard', 'پت درد', 'diarrhea', 'dastana', 'pet kharab'],
            'Inflammatory Bowel Disease (IBD): Pet dard, diarrhea (bloody bhi ho sakta hai), weight loss, fatigue symptoms hain. Gastroenterologist se consult zaroori hai. Treatment: medicines (aminosalicylates, steroids, biologics) ya surgery. Diet modifications help karte hain. Avoid spicy/processed food. Regular follow-up zaroori. TeleCare par gastroenterologist dhoond sakte hain. | IBD: پت درد، diarrhea، weight loss۔ Gastroenterologist se consult۔ Diet modifications۔',
            'How long have you had these digestive symptoms? Any blood in your stool? Recent weight loss?',
            'آپ کو یہ digestive symptoms کتنے دن سے ہیں؟ Stool mein خون آیا ہے؟ Recent weight loss؟',
            'ibd'],

        // MIGRAINE
        [['migraine', 'migrane', 'شدید سر درد', 'severe headache', 'headache one side', 'سر درد ایک طرف'],
            'Migraine: Shadeed sar dard (usually one side), nausea, sensitivity to light/sound symptoms hain. Triggers avoid karain: stress, certain foods, lack of sleep, bright light. OTC: paracetamol ya ibuprofen early stage mein le sakte hain — lekin Neurologist se proper treatment lein. Preventive medicines bhi hotay hain. TeleCare par neurologist dhoond sakte hain. | Migraine: شدید سر درد ایک طرف۔ Triggers avoid کریں۔ Neurologist se treatment لین۔',
            'How often do you get migraines? Do you have any known triggers (food, stress, sleep pattern)?',
            'آپ کو migraine کتنی بار ہوتا ہے؟ کیا آپ کو triggers معلوم ہیں (خوراک، stress، نیند)؟',
            'migraine'],
    ];

    foreach ($rules as $r) {
        if ($has($r[0])) {
            return [
                'reply' => $r[1],
                'followUp' => $is_urdu_msg ? $r[3] : $r[2],
                'followUpUrdu' => $r[3],
                'condition' => $r[4]
            ];
        }
    }

    if (strpos($msg, 'medicine') !== false || strpos($msg, 'tablet') !== false || strpos($msg, 'dawai') !== false || strpos($msg, 'دوائی') !== false || strpos($msg, 'ٹیبلٹ') !== false) {
        return [
            'reply' => 'Never take any medicine without a doctor\'s advice. Some safe general tips: paracetamol for fever/pain, antihistamine for mild allergy — but always consult a doctor first. Find a doctor on TeleCare for proper prescription.',
            'followUp' => $is_urdu_msg ? 'آپ کون سی بیماری/علامت کے لیے دوائیوں کے بارے میں پوچھنا چاہتے ہیں؟' : 'Which specific condition or symptom are you asking medicine for?',
            'condition' => 'medicine'
        ];
    }

    if (strpos($msg, 'book') !== false || strpos($msg, 'appointment') !== false || strpos($msg, 'بُک') !== false || strpos($msg, 'اپائنٹمنٹ') !== false) {
        return [
            'reply' => 'رجسٹریشن کے لیے: Appointment Booking میں اپنا شہر منتخب کریں، ڈاکٹر منتخب کریں، پروفائل دیکھیں اور Book appointment کلک کریں۔ یا "Find doctor" سے علامات کے حساب سے تلاش کریں۔',
            'followUp' => $is_urdu_msg ? 'آپ اپنے شہر میں کون سا specialist دیکھنا چاہتے ہیں؟' : 'Which specialist do you want to see in your city?',
            'condition' => 'booking'
        ];
    }

    if (preg_match('/\b(hi|hello|salam|assalam|سلام|ہیلو|ہائے|آس السلام)\b/', $msg)) {
        return [
            'reply' => "السلام عليكم! میں ٹیلی کیئر کی ہیلتھ اسسٹنٹ ہوں۔ اپنی علامات بتائیں، میں آپ کو درست سپیشلسٹ تجویز کروں گا۔ ہنگامی صورتحال میں 1122 کال کریں۔",
            'followUp' => $is_urdu_msg ? 'آپ کو آج کون سی علامت/بیماری کا سامنا ہے؟' : 'What symptoms or health concern do you have today?',
            'condition' => 'greeting'
        ];
    }

    return [
        'reply' => 'میں علامات اور کون سا ڈاکٹر دیکھنا ہو، یا کن دوائیوں کے بارے میں مدد کر سکتا ہوں۔ اپنے مسئلے کی وضاحت کریں۔ ہنگامی صورت حال میں 1122 کال کریں۔ ہمیشا ڈاکٹر سے مشورہ کر کے دوائی لیں۔',
        'followUp' => $is_urdu_msg ? 'براہ کرم اپنی علامات تفصیل سے بتائیں تاکہ میں آپ کو درست ڈاکٹر تجویز کر سکوں۔' : 'Please describe your symptoms in detail so I can suggest the right doctor.',
        'condition' => 'general'
    ];
}

function callClaudeApi(string $message, string $api_key): ?array
{
    if ($api_key === '' || $api_key === 'YOUR_ANTHROPIC_API_KEY_HERE') {
        return null;
    }

    $system = "You are TeleCare's health assistant for Pakistan. Keep answers short (2-4 sentences). "
        . "For emergencies always mention 1122 and 115. Suggest doctor types and safe OTC medicine options with disclaimer, never diagnose. "
        . "Guide users to use Find doctor and Appointment Booking on the website. "
        . "Always include a disclaimer: consult a doctor before taking any medicine. "
        . "Respond in the same language as the user (English or Urdu). "
        . "Format response as JSON with: 'reply' (main answer), 'followUp' (question to better understand patient in SAME language as user), 'condition' (category keyword). Use plain text, not markdown.";

    $data = [
        'model' => 'claude-sonnet-4-20250514',
        'max_tokens' => 400,
        'system' => $system,
        'messages' => [['role' => 'user', 'content' => $message]],
    ];

    // $ch = curl_init('https://api.anthropic.com/v1/messages');
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions'); # updated for new endpoint
    curl_setopt_array($ch, [ # updated this code block for oprnrouter api-key
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'mistralai/mistral-7b-instruct:free',  // free model
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $message]
            ],
            'max_tokens' => 400,
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
            'HTTP-Referer: http://localhost/telecare',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return null;
    }

    $res = json_decode($response, true);
    $text = $res['choices'][0]['message']['content'] ?? null; # updated for new response format

    if (!$text) {
        return null;
    }

    $jsonStart = strpos($text, '{');
    $jsonEnd = strrpos($text, '}');
    if ($jsonStart !== false && $jsonEnd !== false) {
        $jsonStr = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
        $parsed = json_decode($jsonStr, true);
        if ($parsed && !empty($parsed['reply'])) {
            return $parsed;
        }
    }

    return null;
}

$api_key = 'meta-llama/llama-3.2-3b-instruct:free';

// Priority: Python trained rules → PHP rules → Claude (optional)
$pythonResult = callPythonChatbot($user_message);
$reply = $pythonResult['reply'] ?? null;
$followUp = $pythonResult['followUp'] ?? null;
$condition = $pythonResult['condition'] ?? null;

if ($reply === null) {
    $phpResult = localChatbotReply($user_message);
    $reply = $phpResult['reply'];
    $followUp = $phpResult['followUp'] ?? null;
    $condition = $phpResult['condition'] ?? null;
}

$claude = callClaudeApi($user_message, $api_key);
if ($claude !== null && !empty($claude['reply'])) {
    $reply = $claude['reply'];
    $followUp = $claude['followUp'] ?? $followUp;
    $condition = $claude['condition'] ?? $condition;
}

$is_emergency = (bool) preg_match(
    '/emergency|1122|115|ambulance|heart attack|heartattack|unconscious|stroke|choking|critical|ہنگامی|فوری|دل کا دورہ|دماغ کا دورہ|بے ہوش|سانس نہیں آ رہا|چہرہ ٹیڈھا|بات نہ کر پانا|بہت زیادہ خون بہنا|گھبراہٹ|سخت الرجک/i',
    $user_message
);

if ($condition && !isset($_SESSION['chat_state'])) {
    $_SESSION['chat_state'] = [
        'condition' => $condition,
        'stage' => 0,
        'started_at' => time()
    ];
} elseif ($condition && $_SESSION['chat_state']['condition'] !== $condition) {
    $_SESSION['chat_state'] = [
        'condition' => $condition,
        'stage' => 0,
        'started_at' => time()
    ];
}

echo json_encode([
    'reply' => $reply,
    'followUp' => $followUp,
    'emergency' => $is_emergency,
    'condition' => $condition ?? null,
    'detectedLanguage' => preg_match('/[\u0600-\u06FF]/', $user_message) ? 'urdu' : 'english'
], JSON_UNESCAPED_UNICODE);
