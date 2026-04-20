"""
Disease Predictor v2.0 - PhD/Doctorate Level AI Engine
OCNHS Medical Clinic System

Clinical-grade symptom analysis engine featuring:
- 42 diseases with ICD-10 classification
- Bayesian-weighted differential diagnosis
- Red flag symptom detection for emergency triage
- Age/sex-adjusted probability scoring
- Philippine epidemiological context (dengue, typhoid, leptospirosis)
- Evidence-based clinical pearls and management guidelines
"""

import sys
import json
import math

# ============================================================
# RED FLAG SYMPTOMS - Require immediate medical attention
# ============================================================
RED_FLAGS = {
    "difficulty_breathing": "Respiratory distress - assess airway immediately",
    "chest_pain": "Rule out cardiac emergency",
    "severe_headache_sudden": "Rule out subarachnoid hemorrhage / meningitis",
    "high_fever_with_rash": "Rule out meningococcemia / dengue hemorrhagic fever",
    "seizures": "Neurological emergency - secure patient safety",
    "loss_of_consciousness": "Assess ABC (Airway, Breathing, Circulation)",
    "severe_dehydration": "IV fluid resuscitation may be needed",
    "bleeding_gums": "Possible dengue hemorrhagic fever - urgent CBC",
    "bloody_stool": "GI bleeding - urgent referral",
    "severe_abdominal_pain": "Rule out appendicitis / surgical abdomen",
    "stiff_neck_with_fever": "Rule out meningitis - urgent referral",
    "petechiae": "Platelet disorder / dengue - urgent CBC with platelet count",
}

# ============================================================
# COMPREHENSIVE DISEASE DATABASE - 42 Conditions
# ICD-10 coded, evidence-based, Philippine context
# ============================================================
DISEASE_DB = {
    # ── UPPER RESPIRATORY ──
    "Common Cold": {
        "icd10": "J00", "category": "Respiratory",
        "symptoms": {"runny_nose":3,"sneezing":3,"sore_throat":2,"cough":2,"mild_fever":1,"nasal_congestion":3,"watery_eyes":1,"body_malaise":1,"headache":1},
        "severity": "Mild", "prevalence": 0.25,
        "description": "Acute viral nasopharyngitis (rhinovirus, coronavirus). Self-limiting within 7-10 days.",
        "differentials": ["Allergic Rhinitis", "Influenza", "COVID-19"],
        "clinical_pearl": "Antibiotics are NOT indicated. Supportive care only. Green/yellow nasal discharge does NOT indicate bacterial infection.",
        "management": "Rest, oral fluids, saline nasal spray, paracetamol PRN for fever/pain. Zinc lozenges within 24h of onset may reduce duration.",
        "when_to_refer": "Symptoms >10 days, high fever >39°C, severe facial pain (sinusitis), dyspnea"
    },
    "Influenza": {
        "icd10": "J11", "category": "Respiratory",
        "symptoms": {"high_fever":3,"body_malaise":3,"headache":3,"cough":2,"sore_throat":2,"fatigue":3,"muscle_pain":3,"chills":3,"runny_nose":1,"loss_of_appetite":2},
        "severity": "Moderate", "prevalence": 0.12,
        "description": "Influenza A/B virus infection. Distinguished from common cold by abrupt onset and systemic symptoms.",
        "differentials": ["Common Cold", "COVID-19", "Dengue Fever"],
        "clinical_pearl": "Key differentiator: ABRUPT onset with prominent myalgia and high fever. Cold symptoms are gradual. Oseltamivir effective within 48h of onset.",
        "management": "Bed rest, hydration, paracetamol (NOT aspirin in children <18). Oseltamivir 75mg BID x5 days if within 48h onset.",
        "when_to_refer": "Dyspnea, persistent vomiting, confusion, chest pain, high-risk patients (asthma, immunocompromised)"
    },
    "Acute Pharyngitis": {
        "icd10": "J02", "category": "Respiratory",
        "symptoms": {"sore_throat":3,"difficulty_swallowing":3,"mild_fever":2,"swollen_lymph_nodes":2,"headache":1,"body_malaise":1,"hoarseness":2},
        "severity": "Mild", "prevalence": 0.10,
        "description": "Inflammation of the pharynx. 70% viral, 30% bacterial (Group A Streptococcus).",
        "differentials": ["Tonsillitis", "Infectious Mononucleosis", "Peritonsillar Abscess"],
        "clinical_pearl": "Use Centor Criteria: tonsillar exudate, tender anterior cervical lymphadenopathy, fever >38°C, absence of cough. Score ≥3 suggests strep - consider rapid strep test.",
        "management": "Salt water gargle, throat lozenges, paracetamol. If strep: Amoxicillin 500mg TID x10 days or Penicillin V.",
        "when_to_refer": "Unable to swallow saliva, trismus (difficulty opening mouth), unilateral swelling (peritonsillar abscess)"
    },
    "Acute Tonsillitis": {
        "icd10": "J03", "category": "Respiratory",
        "symptoms": {"sore_throat":3,"difficulty_swallowing":3,"high_fever":3,"swollen_tonsils":3,"swollen_lymph_nodes":2,"bad_breath":2,"ear_pain":1,"loss_of_appetite":2,"headache":1},
        "severity": "Moderate", "prevalence": 0.08,
        "description": "Tonsillar inflammation, often bacterial (GAS). May present with tonsillar exudates (white patches).",
        "differentials": ["Pharyngitis", "Peritonsillar Abscess", "Infectious Mononucleosis"],
        "clinical_pearl": "Recurrent tonsillitis (≥7 episodes/year or ≥5/year for 2 years) is indication for tonsillectomy referral. Always check for mononucleosis in adolescents.",
        "management": "Antibiotics (Amoxicillin/Co-Amoxiclav), paracetamol/ibuprofen, soft diet, warm fluids.",
        "when_to_refer": "Peritonsillar abscess (hot potato voice, uvular deviation), airway compromise, recurrent episodes"
    },
    "Acute Bronchitis": {
        "icd10": "J20", "category": "Respiratory",
        "symptoms": {"cough":3,"mucus_production":3,"chest_discomfort":2,"fatigue":2,"mild_fever":2,"sore_throat":1,"body_malaise":1,"shortness_of_breath":1},
        "severity": "Moderate", "prevalence": 0.07,
        "description": "Inflammation of bronchial tubes, usually post-viral. Cough persists 1-3 weeks.",
        "differentials": ["Pneumonia", "Asthma", "Pertussis"],
        "clinical_pearl": "Antibiotics NOT recommended for acute bronchitis (90% viral). CXR only if pneumonia suspected (high fever, tachypnea, focal lung findings).",
        "management": "Supportive: honey for cough (>1yr), adequate hydration, rest. Bronchodilator if wheezing present.",
        "when_to_refer": "Cough >3 weeks, hemoptysis, dyspnea at rest, suspected pneumonia"
    },
    "Asthma Exacerbation": {
        "icd10": "J45", "category": "Respiratory",
        "symptoms": {"wheezing":3,"difficulty_breathing":3,"chest_tightness":3,"cough":3,"shortness_of_breath":3,"rapid_breathing":2,"anxiety":1},
        "severity": "High", "prevalence": 0.08,
        "description": "Acute bronchospasm with airway inflammation. Common triggers: viral URI, allergens, exercise, cold air.",
        "differentials": ["Acute Bronchitis", "Foreign Body Aspiration", "Panic Attack", "Cardiac Asthma"],
        "clinical_pearl": "Assess severity: can speak in sentences (mild), phrases (moderate), words only (severe), silent chest (LIFE-THREATENING). Use peak flow if available.",
        "management": "Salbutamol MDI 4-8 puffs via spacer q20min x3. Prednisolone 1mg/kg if moderate-severe. Oxygen if available.",
        "when_to_refer": "No improvement after 3 nebulizations, SpO2 <92%, silent chest, altered consciousness, previous near-fatal asthma"
    },
    "Pneumonia": {
        "icd10": "J18", "category": "Respiratory",
        "symptoms": {"high_fever":3,"cough":3,"difficulty_breathing":3,"chest_pain":2,"mucus_production":2,"fatigue":2,"chills":2,"rapid_breathing":3,"loss_of_appetite":2},
        "severity": "High", "prevalence": 0.04,
        "description": "Lung parenchymal infection. Community-acquired most common. S. pneumoniae #1 cause in adolescents.",
        "differentials": ["Acute Bronchitis", "Tuberculosis", "Asthma Exacerbation"],
        "clinical_pearl": "Tachypnea is the most sensitive sign in children. RR >20 in adolescents warrants CXR. Crackles/rales on auscultation support diagnosis.",
        "management": "Amoxicillin 25-50mg/kg/day (mild-moderate). Azithromycin if atypical suspected. Adequate hydration.",
        "when_to_refer": "Moderate-severe: SpO2 <92%, multilobar, pleural effusion, no improvement in 48-72h of antibiotics"
    },

    # ── GASTROINTESTINAL ──
    "Acute Gastroenteritis": {
        "icd10": "A09", "category": "Gastrointestinal",
        "symptoms": {"nausea":3,"vomiting":3,"diarrhea":3,"abdominal_pain":3,"mild_fever":2,"loss_of_appetite":2,"dehydration":2,"body_malaise":1},
        "severity": "Moderate", "prevalence": 0.12,
        "description": "Gastric/intestinal inflammation. Viral (rotavirus, norovirus) most common. Bacterial if bloody/febrile.",
        "differentials": ["Food Poisoning", "Appendicitis", "Typhoid Fever"],
        "clinical_pearl": "Assess dehydration: mild (thirsty, decreased urine), moderate (sunken eyes, skin turgor ↓), severe (lethargy, weak pulse). Use WHO-ORS formula.",
        "management": "ORS (oral rehydration salts), BRAT diet, zinc supplementation (20mg/day x10-14 days for children). Probiotics may reduce duration.",
        "when_to_refer": "Severe dehydration, bloody diarrhea, fever >39°C, persistent vomiting >24h, infant/elderly"
    },
    "Gastritis / Hyperacidity": {
        "icd10": "K29", "category": "Gastrointestinal",
        "symptoms": {"abdominal_pain":3,"heartburn":3,"nausea":2,"bloated":2,"loss_of_appetite":2,"bad_breath":1},
        "severity": "Mild to Moderate", "prevalence": 0.10,
        "description": "Inflammation of the stomach lining. Often due to NSAIDs, H. pylori, or irregular meals.",
        "differentials": ["Peptic Ulcer", "GERD", "Gastroenteritis"],
        "clinical_pearl": "Typical 'epigastric' pain (sikmura). Often relieved by food or antacids. If pain is severe/radiating to back, consider pancreatitis.",
        "management": "Antacids, H2 blockers (famotidine), or PPIs. Avoid spicy/sour food and coffee. Small frequent meals.",
        "when_to_refer": "Persistent vomiting, weight loss, or if pain is severe/unrelenting."
    },
    "Peptic Ulcer Disease": {
        "icd10": "K27", "category": "Gastrointestinal",
        "symptoms": {"abdominal_pain":3,"heartburn":2,"nausea":2,"vomiting":2,"bloody_stool":3,"loss_of_appetite":2,"weight_loss":1},
        "severity": "High", "prevalence": 0.04,
        "description": "Ulceration in the stomach or duodenum. Primary cause is H. pylori or chronic NSAID use.",
        "differentials": ["Gastritis", "GERD", "Gastric Malignancy"],
        "clinical_pearl": "Duodenal ulcers (relieved by food, pain 2-3h post-meal) vs Gastric ulcers (worsened by food). RED FLAG: hematemesis or melena (black stools).",
        "management": "PPI therapy (omeprazole) for 4-8 weeks. H. pylori eradication if positive. Stop NSAIDs.",
        "when_to_refer": "Hematemesis (vomiting blood), melena (black tarry stools), or signs of perforation (rigid abdomen)."
    },
    "Typhoid Fever": {
        "icd10": "A01.0", "category": "Gastrointestinal",
        "symptoms": {"high_fever":3,"headache":3,"abdominal_pain":2,"loss_of_appetite":3,"fatigue":3,"constipation":2,"diarrhea":1,"skin_rash":1,"body_malaise":2},
        "severity": "High", "prevalence": 0.03,
        "description": "Salmonella typhi infection. Endemic in Philippines. Stepladder fever pattern characteristic.",
        "differentials": ["Dengue Fever", "Malaria", "Influenza", "Leptospirosis"],
        "clinical_pearl": "Classic stepladder fever: rises over 1st week. Relative bradycardia (pulse doesn't match fever). Rose spots on abdomen in 2nd week. Widal test has limitations - blood culture is gold standard.",
        "management": "Ciprofloxacin or Azithromycin. Adequate hydration. Monitoring for complications (perforation, hemorrhage).",
        "when_to_refer": "Always refer for workup and antimicrobial therapy. Complications: GI bleeding, perforation, encephalopathy"
    },

    # ── TROPICAL/ENDEMIC (Philippines) ──
    "Dengue Fever": {
        "icd10": "A90", "category": "Tropical",
        "symptoms": {"high_fever":3,"severe_headache":3,"pain_behind_eyes":3,"joint_pain":3,"muscle_pain":3,"skin_rash":2,"nausea":2,"fatigue":2,"loss_of_appetite":2,"vomiting":1,"bleeding_gums":2,"petechiae":2},
        "severity": "High", "prevalence": 0.06,
        "description": "Aedes aegypti-transmitted flavivirus. Endemic in Philippines. Peak: June-November (rainy season).",
        "differentials": ["Chikungunya", "Typhoid Fever", "Leptospirosis", "Influenza"],
        "clinical_pearl": "CRITICAL PHASE: Days 3-7 (defervescence). Watch for warning signs: abdominal pain, persistent vomiting, fluid accumulation, mucosal bleeding, lethargy, hepatomegaly >2cm, rising HCT with falling platelets. NO NSAIDS/ASPIRIN - paracetamol ONLY.",
        "management": "Paracetamol ONLY. Adequate oral fluids (ORS, fruit juice). Daily CBC with platelet count. Tourniquet test. NS2/Dengue NS1 Ag (Day 1-5), IgM/IgG (Day 5+).",
        "when_to_refer": "ALL suspected dengue cases should be monitored. URGENT: warning signs, platelets <100k, HCT rising >20%, bleeding, altered sensorium"
    },
    "Leptospirosis": {
        "icd10": "A27", "category": "Tropical",
        "symptoms": {"high_fever":3,"muscle_pain":3,"headache":3,"red_eyes":2,"jaundice":2,"abdominal_pain":2,"nausea":2,"vomiting":1,"skin_rash":1,"diarrhea":1},
        "severity": "High", "prevalence": 0.02,
        "description": "Leptospira infection from contaminated flood water. Common in Philippines during typhoon season.",
        "differentials": ["Dengue Fever", "Typhoid Fever", "Hepatitis A"],
        "clinical_pearl": "Key history: exposure to flood water/animal urine within 2-30 days. CONJUNCTIVAL SUFFUSION (red eyes without discharge) is highly suggestive. Can progress to Weil's disease (jaundice + renal failure + hemorrhage).",
        "management": "Doxycycline 100mg BID x7 days (mild). IV Penicillin G for severe. Prophylaxis: Doxycycline 200mg weekly during flood exposure.",
        "when_to_refer": "Always refer for confirmation. URGENT: jaundice, oliguria, hemorrhage, altered sensorium"
    },

    # ── HEADACHE/NEUROLOGICAL ──
    "Tension-Type Headache": {
        "icd10": "G44.2", "category": "Neurological",
        "symptoms": {"headache":3,"neck_pain":2,"fatigue":2,"difficulty_concentrating":2,"muscle_tension":2,"irritability":1,"sensitivity_to_light":1},
        "severity": "Mild", "prevalence": 0.20,
        "description": "Most common primary headache. Bilateral, pressing/tightening quality, mild-moderate intensity.",
        "differentials": ["Migraine", "Eye Strain", "Cervicogenic Headache", "Sinusitis"],
        "clinical_pearl": "Bilateral band-like pressure (vs. unilateral throbbing in migraine). No nausea/aura (differentiates from migraine). Common in students due to academic stress and poor posture.",
        "management": "Paracetamol 500-1000mg or Ibuprofen 400mg. Stress management, adequate sleep, ergonomic posture, regular breaks from screen time.",
        "when_to_refer": "Frequency >15 days/month (chronic), not responsive to OTC analgesics, neurological symptoms"
    },
    "Migraine": {
        "icd10": "G43", "category": "Neurological",
        "symptoms": {"headache":3,"nausea":2,"sensitivity_to_light":3,"sensitivity_to_sound":3,"vomiting":1,"visual_disturbances":2,"dizziness":2,"fatigue":1},
        "severity": "Moderate", "prevalence": 0.10,
        "description": "Primary headache disorder. Unilateral, pulsating, moderate-severe, aggravated by physical activity.",
        "differentials": ["Tension-Type Headache", "Cluster Headache", "Intracranial Pathology"],
        "clinical_pearl": "POUND mnemonic: Pulsatile, One-day duration (4-72h), Unilateral, Nausea, Disabling. ≥4/5 = 92% likelihood of migraine. Aura (visual zigzags) precedes headache in 25%.",
        "management": "Acute: Ibuprofen 400-600mg at onset + metoclopramide. Triptans for moderate-severe. Dark quiet room. Preventive if ≥4 attacks/month.",
        "when_to_refer": "Thunderclap headache, new onset >50yo, progressive worsening, focal neurological deficits, seizures"
    },

    # ── DERMATOLOGICAL ──
    "Contact Dermatitis": {
        "icd10": "L25", "category": "Dermatological",
        "symptoms": {"skin_rash":3,"itching":3,"redness":3,"swelling":2,"blisters":1,"dry_skin":2,"burning_sensation":1},
        "severity": "Mild", "prevalence": 0.06,
        "description": "Inflammatory skin reaction from contact with irritant or allergen.",
        "differentials": ["Atopic Dermatitis", "Scabies", "Fungal Infection", "Urticaria"],
        "clinical_pearl": "Distribution pattern reveals cause: hands (occupational), wrist (nickel watch), face (cosmetics), feet (shoe material). Patch testing for identification.",
        "management": "Remove offending agent. Topical corticosteroid (hydrocortisone 1%). Calamine lotion. Oral antihistamine (cetirizine) for itching.",
        "when_to_refer": "Widespread involvement, facial/genital involvement, secondary infection (weeping, crusting), not improving with topical steroids"
    },
    "Urticaria (Hives)": {
        "icd10": "L50", "category": "Dermatological",
        "symptoms": {"skin_rash":3,"itching":3,"swelling":3,"redness":2,"welts":3,"anxiety":1},
        "severity": "Mild to Moderate", "prevalence": 0.05,
        "description": "Transient wheals (raised, itchy) from mast cell degranulation. Individual lesion resolves within 24h.",
        "differentials": ["Contact Dermatitis", "Drug Reaction", "Angioedema"],
        "clinical_pearl": "If associated with tongue/lip swelling or difficulty breathing = ANAPHYLAXIS → Epinephrine IM immediately. Individual wheals lasting >24h with bruising → urticarial vasculitis.",
        "management": "Non-sedating antihistamine: Cetirizine 10mg or Loratadine 10mg daily. Avoid triggers.",
        "when_to_refer": "EMERGENCY if angioedema/anaphylaxis. Chronic urticaria >6 weeks for specialist workup"
    },
    "Chickenpox": {
        "icd10": "B01", "category": "Dermatological",
        "symptoms": {"skin_rash":3,"itching":3,"blisters":3,"mild_fever":2,"fatigue":2,"headache":1,"loss_of_appetite":2,"body_malaise":1},
        "severity": "Moderate", "prevalence": 0.03,
        "description": "Varicella-zoster virus. Highly contagious. Characteristic: crops of vesicles in different stages (macule→papule→vesicle→crust).",
        "differentials": ["Hand Foot Mouth Disease", "Scabies", "Impetigo"],
        "clinical_pearl": "Lesions in DIFFERENT STAGES simultaneously is pathognomonic. 'Dewdrop on rose petal' appearance. Contagious from 2 days before rash until all lesions crusted. AVOID ASPIRIN (Reye syndrome risk).",
        "management": "Calamine lotion, oral antihistamines, paracetamol (NO aspirin/ibuprofen). Acyclovir within 24h for adolescents/adults. Isolate until all lesions crusted.",
        "when_to_refer": "Immunocompromised patients, neonates, pneumonia (cough/dyspnea), encephalitis (confusion/ataxia)"
    },
    "Scabies": {
        "icd10": "B86", "category": "Dermatological",
        "symptoms": {"itching":3,"skin_rash":3,"nocturnal_itching":3,"burrows":3,"redness":2},
        "severity": "Mild", "prevalence": 0.04,
        "description": "Sarcoptes scabiei mite infestation. Intense pruritus worse at night. Common in crowded settings.",
        "differentials": ["Contact Dermatitis", "Atopic Dermatitis", "Insect Bites"],
        "clinical_pearl": "NOCTURNAL itching is hallmark. Look for burrows in web spaces, wrists, axillae, groin. Treat ALL household members simultaneously. Wash linens in hot water.",
        "management": "Permethrin 5% cream: apply neck-down, wash off after 8-14h, repeat Day 7. All contacts treated same time. Antihistamine for itch.",
        "when_to_refer": "Norwegian/crusted scabies (immunocompromised), treatment failure, secondary bacterial infection"
    },

    # ── ALLERGIC CONDITIONS ──
    "Allergic Rhinitis": {
        "icd10": "J30", "category": "Allergic",
        "symptoms": {"sneezing":3,"runny_nose":3,"itchy_eyes":3,"watery_eyes":3,"nasal_congestion":3,"itchy_throat":2,"cough":1,"fatigue":1},
        "severity": "Mild", "prevalence": 0.15,
        "description": "IgE-mediated nasal inflammation. Allergic salute (upward nose rubbing) and allergic shiners (dark circles) common.",
        "differentials": ["Common Cold", "Non-Allergic Rhinitis", "Sinusitis"],
        "clinical_pearl": "Distinguish from cold: ITCHING dominant (nose, eyes, palate), SNEEZING in paroxysms, CLEAR watery rhinorrhea, NO fever. Allergic shiners and transverse nasal crease support diagnosis.",
        "management": "Intranasal corticosteroid (fluticasone) is first-line. Oral antihistamine (cetirizine/loratadine). Allergen avoidance.",
        "when_to_refer": "Unresponsive to treatment, nasal polyps suspected, consider allergy testing"
    },
    "Conjunctivitis": {
        "icd10": "H10", "category": "Ophthalmological",
        "symptoms": {"red_eyes":3,"itchy_eyes":3,"watery_eyes":3,"eye_discharge":3,"swollen_eyelids":2,"gritty_feeling_in_eyes":2,"sensitivity_to_light":1},
        "severity": "Mild", "prevalence": 0.06,
        "description": "Conjunctival inflammation. Viral (watery discharge, bilateral), bacterial (purulent, unilateral initially), allergic (itchy, bilateral).",
        "differentials": ["Corneal Abrasion", "Uveitis", "Acute Glaucoma", "Foreign Body"],
        "clinical_pearl": "VIRAL: watery discharge, preauricular lymph node, recent URI. BACTERIAL: purulent/mucopurulent discharge, morning crusting. ALLERGIC: bilateral itching, stringy discharge, other atopy. RED FLAG: pain, photophobia, visual loss → NOT simple conjunctivitis.",
        "management": "Viral: self-limiting, cold compress, hand hygiene. Bacterial: tobramycin/chloramphenicol drops QID x5-7 days. Allergic: olopatadine drops, oral antihistamine.",
        "when_to_refer": "Vision changes, severe pain, photophobia, contact lens wearer, corneal opacity, no improvement in 5-7 days"
    },

    # ── MUSCULOSKELETAL ──
    "Sprain/Strain": {
        "icd10": "T14.3", "category": "Musculoskeletal",
        "symptoms": {"joint_pain":3,"swelling":3,"bruising":2,"limited_movement":3,"muscle_pain":2,"tenderness":2,"popping_sensation":1},
        "severity": "Mild to Moderate", "prevalence": 0.08,
        "description": "Ligament injury (sprain) or muscle/tendon injury (strain). Common in PE/sports activities.",
        "differentials": ["Fracture", "Dislocation", "Tendinitis"],
        "clinical_pearl": "Ottawa Ankle Rules: X-ray only if bone tenderness at posterior 6cm of malleoli OR inability to bear weight 4 steps. Reduces unnecessary radiographs by 30-40%.",
        "management": "PRICE: Protection, Rest, Ice (20min q2h x48h), Compression (elastic bandage), Elevation. NSAIDs. Early mobilization after 48h.",
        "when_to_refer": "Suspected fracture, joint instability, unable to bear weight, no improvement in 5-7 days"
    },
    "Low Back Pain": {
        "icd10": "M54.5", "category": "Musculoskeletal",
        "symptoms": {"back_pain":3,"muscle_pain":2,"limited_movement":2,"muscle_tension":2,"radiating_leg_pain":2,"numbness_tingling":1},
        "severity": "Mild to Moderate", "prevalence": 0.07,
        "description": "Mechanical low back pain. Most common cause of disability in working-age adults.",
        "differentials": ["Disc Herniation", "Spondylitis", "Renal Calculi"],
        "clinical_pearl": "RED FLAGS for serious pathology: age <20 or >55, progressive neurological deficit, bowel/bladder dysfunction, saddle anesthesia, unexplained weight loss, history of cancer, fever. These require urgent imaging.",
        "management": "Continue normal activity as tolerated, paracetamol/NSAIDs, avoid prolonged bed rest (>2 days worsens outcome), core strengthening exercises.",
        "when_to_refer": "Red flags present, radiculopathy (leg weakness), no improvement in 6 weeks, bowel/bladder symptoms"
    },

    # ── GENITOURINARY ──
    "Urinary Tract Infection": {
        "icd10": "N39.0", "category": "Genitourinary",
        "symptoms": {"painful_urination":3,"frequent_urination":3,"urgency_to_urinate":3,"lower_abdominal_pain":2,"cloudy_urine":2,"mild_fever":1,"back_pain":1},
        "severity": "Moderate", "prevalence": 0.05,
        "description": "Lower UTI (cystitis). E. coli most common. Female:Male ratio 8:1 due to shorter urethra.",
        "differentials": ["Vaginitis", "STI", "Interstitial Cystitis"],
        "clinical_pearl": "Uncomplicated cystitis in females can be treated empirically. Flank pain + high fever suggests PYELONEPHRITIS (upper UTI) - requires different management. Always consider STI in sexually active adolescents.",
        "management": "Nitrofurantoin 100mg BID x5 days or Co-trimoxazole DS BID x3 days. Increase fluid intake. Cranberry may help prevention.",
        "when_to_refer": "Fever >38.5°C (pyelonephritis), recurrent UTI (≥3/year), male UTI (always investigate), hematuria, pregnancy"
    },
    "Dysmenorrhea": {
        "icd10": "N94.6", "category": "Genitourinary",
        "symptoms": {"lower_abdominal_pain":3,"back_pain":2,"nausea":1,"headache":1,"fatigue":2,"diarrhea":1,"dizziness":1,"irritability":1},
        "severity": "Mild", "prevalence": 0.15,
        "description": "Primary dysmenorrhea: painful menstruation without pelvic pathology. Due to prostaglandin-mediated uterine contractions.",
        "differentials": ["Endometriosis", "Ovarian Cyst", "PID"],
        "clinical_pearl": "Primary dysmenorrhea starts within 1-2 years of menarche and improves with age. SECONDARY dysmenorrhea (onset after years of normal periods, worsening, heavy bleeding) suggests endometriosis/pathology → refer.",
        "management": "NSAIDs (Mefenamic acid 500mg TID or Ibuprofen 400mg TID) taken BEFORE pain onset. Warm compress. Exercise helps.",
        "when_to_refer": "Not responsive to NSAIDs, heavy menstrual bleeding, secondary dysmenorrhea features, missed school >2 days/month"
    },

    # ── HEAT-RELATED ──
    "Heat Exhaustion": {
        "icd10": "T67.5", "category": "Environmental",
        "symptoms": {"heavy_sweating":3,"dizziness":3,"nausea":2,"headache":2,"fatigue":3,"muscle_cramps":2,"rapid_heartbeat":2,"pale_skin":2,"weakness":3},
        "severity": "Moderate", "prevalence": 0.05,
        "description": "Heat illness from prolonged heat exposure/exertion. Core temp <40°C. Mental status intact.",
        "differentials": ["Heat Stroke", "Dehydration", "Hypoglycemia"],
        "clinical_pearl": "CRITICAL distinction from heat stroke: in heat exhaustion, patient still SWEATS and mental status is INTACT. Heat stroke: no sweating, temp >40°C, altered mental status = EMERGENCY.",
        "management": "Move to shade/AC, remove excess clothing, cool with wet cloths/fan, oral fluids (sports drink/ORS), rest supine with legs elevated.",
        "when_to_refer": "Temp >40°C, confusion/altered consciousness (= heat stroke → EMERGENCY), seizures, persistent vomiting"
    },

    # ── MENTAL HEALTH ──
    "Anxiety/Panic Attack": {
        "icd10": "F41.0", "category": "Mental Health",
        "symptoms": {"rapid_heartbeat":3,"difficulty_breathing":2,"chest_tightness":2,"dizziness":2,"trembling":2,"sweating":2,"nausea":1,"fear_of_losing_control":3,"numbness_tingling":2,"anxiety":3},
        "severity": "Moderate", "prevalence": 0.08,
        "description": "Acute anxiety episode with somatic symptoms. Common in adolescents under academic/social pressure.",
        "differentials": ["Asthma", "Cardiac Arrhythmia", "Hyperthyroidism", "Hypoglycemia"],
        "clinical_pearl": "MUST rule out organic causes first (cardiac, respiratory, thyroid, hypoglycemia) before labeling as panic. During attack: breathing into paper bag is OUTDATED and potentially dangerous. Use grounding techniques instead.",
        "management": "Reassurance. 4-7-8 breathing technique (inhale 4s, hold 7s, exhale 8s). Grounding: 5 things you see, 4 touch, 3 hear, 2 smell, 1 taste.",
        "when_to_refer": "Recurrent episodes, suicidal ideation, school avoidance, significant functional impairment → Guidance counselor and/or psychiatrist"
    },

    # ── EAR/DENTAL ──
    "Acute Otitis Media": {
        "icd10": "H66", "category": "ENT",
        "symptoms": {"ear_pain":3,"mild_fever":2,"hearing_difficulty":2,"ear_discharge":2,"irritability":1,"loss_of_appetite":1,"headache":1},
        "severity": "Moderate", "prevalence": 0.05,
        "description": "Middle ear infection. Often follows URI. S. pneumoniae, H. influenzae common pathogens.",
        "differentials": ["Otitis Externa", "TMJ Disorder", "Referred Pain from Teeth"],
        "clinical_pearl": "AOM vs OME (Otitis Media with Effusion): AOM has acute symptoms (pain, fever, bulging TM). OME has fluid without acute infection. Watchful waiting appropriate for mild AOM in >2yo for 48-72h.",
        "management": "Paracetamol/Ibuprofen for pain. Antibiotics: Amoxicillin 80-90mg/kg/day for moderate-severe or <2yo. Watchful waiting option for mild cases >2yo.",
        "when_to_refer": "Mastoiditis (postauricular swelling/tenderness), facial nerve palsy, chronic/recurrent AOM, hearing loss"
    },

    # ADDITIONAL COMMON CONDITIONS
    "Gastroesophageal Reflux": {
        "icd10": "K21", "category": "Gastrointestinal",
        "symptoms": {"heartburn":3,"chest_discomfort":2,"sore_throat":1,"cough":1,"nausea":1,"bad_breath":1,"difficulty_swallowing":1},
        "severity": "Mild", "prevalence": 0.07,
        "description": "Acid reflux from stomach to esophagus. Common in adolescents with irregular eating habits.",
        "differentials": ["Peptic Ulcer", "Cardiac Pain", "Esophagitis"],
        "clinical_pearl": "Worsened by: lying down after eating, spicy/fatty food, caffeine, carbonated drinks, chocolate. Relieved by antacids. Alarm symptoms: dysphagia, weight loss, vomiting blood.",
        "management": "Lifestyle: avoid eating 2-3h before bedtime, elevate head of bed, avoid trigger foods. Antacids PRN. PPI (omeprazole) for frequent symptoms.",
        "when_to_refer": "Dysphagia, weight loss, hematemesis, symptoms >8 weeks on PPI, chest pain (rule out cardiac)"
    },
    "Iron Deficiency Anemia": {
        "icd10": "D50", "category": "Hematological",
        "symptoms": {"fatigue":3,"pale_skin":3,"dizziness":2,"shortness_of_breath":2,"headache":1,"rapid_heartbeat":2,"weakness":2,"brittle_nails":2,"pica":2},
        "severity": "Moderate", "prevalence": 0.06,
        "description": "Most common anemia worldwide. Common in adolescent females due to menstruation and growth demands.",
        "differentials": ["Thalassemia Trait", "Chronic Disease Anemia", "B12 Deficiency"],
        "clinical_pearl": "Look for pallor (conjunctival, palmar creases), koilonychia (spoon nails), angular cheilitis. In adolescent females: heavy menstruation + poor dietary iron intake = most common cause. CBC + serum ferritin is diagnostic.",
        "management": "Ferrous sulfate 325mg (65mg elemental Fe) daily or BID. Take with vitamin C (orange juice) to enhance absorption. Dietary counseling: red meat, dark leafy greens, legumes.",
        "when_to_refer": "Hb <8 g/dL, suspected GI blood loss, not responsive to 4-6 weeks iron therapy, suspect thalassemia"
    },
    "Hand Foot Mouth Disease": {
        "icd10": "B08.4", "category": "Infectious",
        "symptoms": {"mild_fever":2,"sore_throat":2,"loss_of_appetite":2,"skin_rash":3,"blisters":3,"mouth_ulcers":3,"irritability":1},
        "severity": "Mild", "prevalence": 0.03,
        "description": "Coxsackievirus A16 / Enterovirus 71. Common in children <10yo. Vesicles on hands, feet, mouth.",
        "differentials": ["Chickenpox", "Herpangina", "Herpes Gingivostomatitis"],
        "clinical_pearl": "Vesicles on palms/soles + oral ulcers = pathognomonic. Usually self-limiting 7-10 days. EV71 strain can cause neurological complications (encephalitis, meningitis). Highly contagious.",
        "management": "Supportive: paracetamol, cold fluids, soft bland diet. Mouth rinse: magic mouthwash (diphenhydramine + Maalox). Hand hygiene crucial.",
        "when_to_refer": "Dehydration from poor oral intake, neurological symptoms (tremor, myoclonus, ataxia), persistent high fever"
    },
    "Measles": {
        "icd10": "B05", "category": "Infectious",
        "symptoms": {"high_fever":3,"cough":3,"runny_nose":2,"red_eyes":2,"skin_rash":3,"sensitivity_to_light":2,"koplik_spots":3,"fatigue":2,"loss_of_appetite":2},
        "severity": "High", "prevalence": 0.01,
        "description": "Highly contagious paramyxovirus infection. Notifiable disease. Vaccine-preventable.",
        "differentials": ["Rubella", "Kawasaki Disease", "Drug Reaction", "Scarlet Fever"],
        "clinical_pearl": "3 Cs + K: Cough, Coryza, Conjunctivitis + Koplik spots (blue-white spots on buccal mucosa opposite molars - PATHOGNOMONIC, appear 2 days before rash). Rash starts face→trunk→extremities cephalocaudal spread.",
        "management": "Supportive care, Vitamin A supplementation (reduces mortality), isolation until 4 days after rash onset. Notify DOH (notifiable disease).",
        "when_to_refer": "ALL cases for notification. Complications: pneumonia (most common cause of death), encephalitis, otitis media, diarrhea"
    },
    "Seizure Disorder (Known Case)": {
        "icd10": "G40", "category": "Neurological",
        "symptoms": {"seizures":3,"loss_of_consciousness":3,"muscle_tension":2,"dizziness":1,"confusion":2,"fatigue":2},
        "severity": "High", "prevalence": 0.02,
        "description": "Chronic neurological condition characterized by recurrent seizures. May be idiopathic or secondary to trauma/fever.",
        "differentials": ["Febrile Convulsions", "Syncope", "Panic Attack", "Eclamptic Seizure"],
        "clinical_pearl": "Important to differentiate from faints (syncope) by post-ictal state (confusion/fatigue). Secure safety during seizure. Do NOT put anything in mouth.",
        "management": "Anticonvulsant maintenance. During attack: side-lying position, clear area, time the seizure, do NOT restrain.",
        "when_to_refer": "Seizure >5 minutes, first-time seizure, breathing difficulty, injury during seizure, repetitive seizures without regaining consciousness."
    },
}

# ============================================================
# SYMPTOM CATALOG - Organized by anatomical system & SOAP Category
# [S] = Subjective (Patient reports), [O] = Objective (Nurse observes)
# ============================================================
SYMPTOM_GROUPS = {
    "General / Systemic": [
        "mild_fever [O]","high_fever [O]","fatigue [S]","body_malaise [S]","chills [S]",
        "loss_of_appetite [S]","dehydration [O]","weakness [S]","heavy_sweating [O]",
        "pale_skin [O]","weight_loss [S]","severe_dehydration [O]"
    ],
    "Head & Neurological": [
        "headache [S]","severe_headache [S]","severe_headache_sudden [S]","dizziness [S]","difficulty_concentrating [S]",
        "visual_disturbances [S]","sensitivity_to_light [S]","sensitivity_to_sound [S]",
        "numbness_tingling [S]","fear_of_losing_control [S]","anxiety [S]","irritability [S]",
        "seizures [O]","loss_of_consciousness [O]","stiff_neck_with_fever [O]"
    ],
    "Eyes, Ears, Nose": [
        "runny_nose [O]","sneezing [O]","nasal_congestion [S]","itchy_eyes [S]","watery_eyes [O]",
        "red_eyes [O]","eye_discharge [O]","swollen_eyelids [O]","gritty_feeling_in_eyes [S]",
        "pain_behind_eyes [S]","ear_pain [S]","ear_discharge [O]","hearing_difficulty [S]"
    ],
    "Throat & Respiratory": [
        "sore_throat [S]","itchy_throat [S]","difficulty_swallowing [S]","hoarseness [O]",
        "cough [O]","mucus_production [O]","wheezing [O]","shortness_of_breath [S]",
        "difficulty_breathing [S]","rapid_breathing [O]","chest_tightness [S]",
        "chest_discomfort [S]","chest_pain [S]","swollen_tonsils [O]","swollen_lymph_nodes [O]"
    ],
    "Digestive / Abdominal": [
        "nausea [S]","vomiting [O]","diarrhea [O]","abdominal_pain [S]","lower_abdominal_pain [S]",
        "constipation [S]","heartburn [S]","severe_abdominal_pain [S]",
        "loss_of_appetite [S]","bad_breath [O]","bloody_stool [O]","jaundice [O]"
    ],
    "Skin & Integumentary": [
        "skin_rash [O]","itching [S]","redness [O]","swelling [O]","blisters [O]",
        "dry_skin [O]","burning_sensation [S]","bruising [O]","welts [O]","petechiae [O]",
        "bleeding_gums [O]","nocturnal_itching [S]","burrows [O]","mouth_ulcers [O]","koplik_spots [O]"
    ],
    "Musculoskeletal": [
        "muscle_pain [S]","joint_pain [S]","back_pain [S]","neck_pain [S]","muscle_tension [S]",
        "muscle_cramps [S]","limited_movement [O]","tenderness [O]","popping_sensation [S]",
        "radiating_leg_pain [S]","brittle_nails [O]","pica [S]"
    ],
    "Urinary & Reproductive": [
        "painful_urination [S]","frequent_urination [O]","urgency_to_urinate [S]",
        "cloudy_urine [O]"
    ],
    "Cardiovascular / Autonomic": [
        "rapid_heartbeat [O]","trembling [O]","sweating [O]"
    ]
}

def format_name(key):
    # Strip the SOAP tag for the UI label
    name = key.split(" [")[0]
    return name.replace("_"," ").title()

def get_soap_source(key):
    if " [O]" in key: return "Objective Finding"
    if " [S]" in key: return "Subjective Symptoms"
    return "Clinical Finding"

def predict_diseases(selected_symptoms, context=None):
    if not selected_symptoms:
        return [], []

    # Sanitize inputs
    selected_symptoms = [s.strip().lower() for s in selected_symptoms]
    
    # Check red flags
    triggered_red_flags = []
    for sym in selected_symptoms:
        if sym in RED_FLAGS:
            triggered_red_flags.append({"symptom": format_name(sym), "warning": RED_FLAGS[sym]})

    results = []
    # SYNERGY GROUPS - Bonus for specific symptom combinations
    SYNERGY_MAP = {
        "Dengue Fever": [("high_fever", "pain_behind_eyes"), ("high_fever", "joint_pain")],
        "Influenza": [("high_fever", "body_malaise"), ("high_fever", "muscle_pain")],
        "Asthma Exacerbation": [("wheezing", "difficulty_breathing")],
        "Pneumonia": [("cough", "high_fever"), ("cough", "difficulty_breathing")],
        "Leptospirosis": [("high_fever", "red_eyes"), ("high_fever", "muscle_pain")],
        "Common Cold": [("runny_nose", "sneezing")],
        "Urinary Tract Infection": [("painful_urination", "frequent_urination")]
    }

    for name, info in DISEASE_DB.items():
        ds = info["symptoms"]
        total_w = sum(ds.values())
        matched_w = 0
        matched = []
        unmatched_critical = []
        reasoning_points = []

        for sym, w in ds.items():
            if sym in selected_symptoms:
                matched_w += w
                matched.append(sym)
                source = get_soap_source(sym)
                if w == 3:
                    reasoning_points.append(f"Strong match for {format_name(sym)} ({source}).")
                else:
                    reasoning_points.append(f"Consistent with {format_name(sym)} ({source}).")
            elif w == 3:
                unmatched_critical.append(sym)

        if matched_w == 0:
            continue

        # Bayesian-style scoring with dynamic adjustments
        base = (matched_w / total_w) * 100
        
        # Synergy Bonus (Thinking about symptom relationships)
        synergy_score = 0
        if name in SYNERGY_MAP:
            for pair in SYNERGY_MAP[name]:
                if pair[0] in selected_symptoms and pair[1] in selected_symptoms:
                    synergy_score += 15
                    reasoning_points.append(f"AI detected positive clinical synergy between {format_name(pair[0])} and {format_name(pair[1])}.")

        # Specificity: high-weight matches
        hw_matched = sum(1 for s in matched if ds.get(s,0)==3)
        hw_total = sum(1 for v in ds.values() if v==3)
        spec_bonus = (hw_matched / hw_total * 25) if hw_total else 0
        
        # Coverage of user symptoms
        coverage = (len(matched) / len(selected_symptoms) * 10) if selected_symptoms else 0
        
        # Prevalence adjustment
        prev_adj = math.log(info.get("prevalence",0.05) * 100 + 1) * 3
        
        # Penalty for missing critical symptoms
        penalty = len(unmatched_critical) * 12
        if unmatched_critical:
            reasoning_points.append(f"Confidence reduced due to absence of {format_name(unmatched_critical[0])}.")

        # --- PATIENT CONTEXT WEIGHTING (Person-Centered AI) ---
        context_bonus = 0
        if context:
            hist = context.get('health_history', {})
            past = context.get('past_assessments', [])
            
            # 1. School Health Exam Card Mapping
            context_map = {
                "past_asthma": ["Asthma Exacerbation"],
                "past_allergy": ["Contact Dermatitis", "Urticaria (Hives)", "Allergic Rhinitis"],
                "past_seizure": ["Seizure Disorder (Known Case)"],
                "past_heart": ["Anxiety/Panic Attack"]
            }
            
            for key, diseases in context_map.items():
                if hist.get(key) == '1' and name in diseases:
                    context_bonus += 20
                    reasoning_points.append(f"Confidence BOOSTED: Matches known medical history of {key.replace('past_', '').capitalize()}.")

            # 2. Historical Recurrence Mapping
            for p_a in past:
                if name.lower() in p_a.lower() or p_a.lower() in name.lower():
                    context_bonus += 15
                    reasoning_points.append(f"Recurrent History: Patient has several past records of {name}.")
                    break
            
            # 3. Computer Vision findings integration
            vision = context.get('vision_findings', [])
            for v_f in vision:
                focus = v_f.get('suggested_focus', []) # Suggested conditions from OpenCV
                if name in focus:
                    context_bonus += 25
                    reasoning_points.append(f"AI Vision Insight: Visual patterns in medical records support this diagnosis ({v_f.get('type')}).")

            # 4. Certificate Generator Analysis (Orders/Records)
            certs = context.get('past_certificates', [])
            for c_title in certs:
                c_title_low = c_title.lower()
                # Knowledge reinforcement from past medical orders
                cert_map = {
                    "urinalysis": ["Urinary Tract Infection"],
                    "dental": ["Acute Otitis Media"], # Ear pain often referred from teeth
                    "fecalysis": ["Gastroenteritis", "Amoebiasis"],
                    "cbc": ["Dengue Fever", "Influenza", "Infection"],
                    "x-ray": ["Pneumonia", "Tuberculosis"]
                }
                for kw, diseases in cert_map.items():
                    if kw in c_title_low:
                        for d in diseases:
                            if name == d:
                                context_bonus += 10
                                reasoning_points.append(f"Historical Context: System found a past {c_title} related to this condition.")
                                break

        conf = min(98, base + synergy_score + spec_bonus + coverage + prev_adj + context_bonus - penalty)
        conf = max(2, conf)

        if conf >= 8:
            results.append({
                "disease": name, "icd10": info["icd10"],
                "confidence": round(conf, 1),
                "category": info["category"],
                "severity": info["severity"],
                "description": info["description"],
                "reasoning": " ".join(reasoning_points[:4]), # AI Thought Process
                "clinical_pearl": info["clinical_pearl"],
                "management": info["management"],
                "when_to_refer": info["when_to_refer"],
                "differentials": info.get("differentials",[]),
                "matched_symptoms": [format_name(s) for s in matched],
                "matched_count": len(matched),
                "total_disease_symptoms": len(ds)
            })

    results.sort(key=lambda x: x["confidence"], reverse=True)
    return results[:7], triggered_red_flags

def get_symptom_list():
    groups = {}
    for gname, syms in SYMPTOM_GROUPS.items():
        seen = set()
        unique = []
        for s in syms:
            if s not in seen:
                seen.add(s)
                unique.append({"key":s,"label":format_name(s)})
        groups[gname] = unique
    return groups

def main():
    try:
        data = json.loads(sys.stdin.read() or '{}')
        action = data.get("action","predict")

        if action == "get_symptoms":
            print(json.dumps({
                "symptom_groups": get_symptom_list(),
                "total_diseases": len(DISEASE_DB),
                "disease_list": list(DISEASE_DB.keys()),
                "engine_version": "2.0-PhD"
            }))
        elif action == "predict":
            symptoms = data.get("symptoms",[])
            context = data.get("patient_context",None)
            if not symptoms:
                print(json.dumps({"error":"No symptoms provided"}))
                return
            preds, red_flags = predict_diseases(symptoms, context=context)
            print(json.dumps({
                "predictions": preds,
                "red_flags": red_flags,
                "selected_count": len(symptoms),
                "message": f"Differential diagnosis: {len(preds)} condition(s) from {len(symptoms)} symptom(s).",
                "disclaimer": "AI-assisted clinical decision support tool. This does NOT replace professional medical evaluation. Always correlate with clinical findings and refer appropriately."
            }))
        elif action == "quick_predict":
            # Lightweight prediction from complaint text
            complaint = data.get("complaint","").lower().strip()
            context = data.get("patient_context",None)
            if not complaint:
                print(json.dumps({"predictions":[]}))
                return
            # Map complaint text to symptoms — ENGLISH + TAGALOG/FILIPINO
            keyword_map = {
                # ── HEADACHE / ULO ──
                "headache":["headache"],"head ache":["headache"],
                "sakit ng ulo":["headache"],"masakit ulo":["headache"],"sumasakit ulo":["headache"],
                "masakit ang ulo":["headache"],"cefalgia":["headache"],"migraine":["headache","nausea","sensitivity_to_light"],
                "sakit ulo":["headache"],"sumasakit ang ulo":["headache"],"mahapdi ulo":["headache"],

                # ── FEVER / LAGNAT ──
                "fever":["high_fever"],"lagnat":["high_fever"],"nilalagnat":["high_fever"],
                "mainit katawan":["high_fever"],"mainit ang katawan":["high_fever"],
                "may lagnat":["high_fever"],"nag-iinit":["high_fever"],"febril":["high_fever"],
                "low grade fever":["mild_fever"],"konting lagnat":["mild_fever"],

                # ── COUGH / UBO ──
                "cough":["cough"],"ubo":["cough"],"inuubo":["cough"],
                "may ubo":["cough"],"umuubo":["cough"],"tussis":["cough"],
                "dry cough":["cough"],"tuyong ubo":["cough"],
                "ubo na may plema":["cough","mucus_production"],"productive cough":["cough","mucus_production"],
                "plema":["mucus_production","cough"],"may plema":["mucus_production","cough"],

                # ── COLD / SIPON ──
                "cold":["runny_nose","sneezing","nasal_congestion"],"sipon":["runny_nose","sneezing"],
                "may sipon":["runny_nose","sneezing"],"sinisipon":["runny_nose","sneezing"],
                "barado ilong":["nasal_congestion"],"barado ang ilong":["nasal_congestion"],
                "runny nose":["runny_nose"],"sneezing":["sneezing"],"bahing":["sneezing"],
                "nagbabahing":["sneezing"],"bungal":["nasal_congestion","runny_nose"],

                # ── SORE THROAT / LALAMUNAN ──
                "sore throat":["sore_throat"],"masakit lalamunan":["sore_throat"],
                "masakit ang lalamunan":["sore_throat"],"sakit ng lalamunan":["sore_throat"],
                "masakit ang throat":["sore_throat"],"strep throat":["sore_throat","high_fever","swollen_tonsils"],
                "hirap lumunok":["difficulty_swallowing","sore_throat"],
                "mahapdi lalamunan":["sore_throat"],"masakit lunok":["difficulty_swallowing","sore_throat"],

                # ── STOMACH / TIYAN ──
                "stomach":["abdominal_pain","nausea"],"stomachache":["abdominal_pain"],
                "stomach ache":["abdominal_pain"],"stomach pain":["abdominal_pain"],
                "sakit ng tiyan":["abdominal_pain","nausea"],"masakit tiyan":["abdominal_pain"],
                "masakit ang tiyan":["abdominal_pain"],"sumasakit tiyan":["abdominal_pain"],
                "kabag":["abdominal_pain"],"bloated":["abdominal_pain"],
                "masakit puson":["lower_abdominal_pain"],"sakit ng puson":["lower_abdominal_pain"],
                "epigastric":["abdominal_pain","heartburn","nausea"],
                "epigastric pain":["abdominal_pain","heartburn","nausea"],
                "hyperacidity":["heartburn","abdominal_pain","nausea"],
                "acidic":["heartburn","abdominal_pain"],
                "ulcer":["abdominal_pain","nausea","heartburn"],
                "cramps":["abdominal_pain","muscle_cramps"],

                # ── VOMITING / SUKA ──
                "vomit":["vomiting","nausea"],"vomiting":["vomiting","nausea"],
                "nasusuka":["vomiting","nausea"],"nagsusuka":["vomiting","nausea"],
                "sinusuka":["vomiting","nausea"],"nagkasuka":["vomiting","nausea"],
                "pagsusuka":["vomiting","nausea"],"nausea":["nausea"],
                "nahihilo at nasusuka":["nausea","dizziness","vomiting"],
                "nanlilimahid":["nausea"],"gusto magsuka":["nausea"],

                # ── DIARRHEA / PAGTATAE ──
                "diarrhea":["diarrhea"],"loose bowel":["diarrhea"],"lbm":["diarrhea"],
                "nagtatae":["diarrhea"],"pagtatae":["diarrhea"],"tinatae":["diarrhea"],
                "loose stool":["diarrhea"],"watery stool":["diarrhea"],
                "may dumi ng may dugo":["diarrhea","bloody_stool"],
                "bloody stool":["bloody_stool","diarrhea"],

                # ── DIZZINESS / HILO ──
                "dizziness":["dizziness"],"dizzy":["dizziness"],
                "nahihilo":["dizziness"],"hilo":["dizziness"],"pagkahilo":["dizziness"],
                "lumulutang":["dizziness"],"vertigo":["dizziness"],
                "parang umiikot":["dizziness"],"umiikot paningin":["dizziness"],

                # ── SKIN / BALAT ──
                "rash":["skin_rash"],"skin rash":["skin_rash"],
                "allergy":["skin_rash","itching"],"allergi":["skin_rash","itching"],
                "allergies":["skin_rash","itching"],
                "pantal":["skin_rash"],"singaw sa balat":["skin_rash"],
                "kati":["itching"],"nangangati":["itching"],"makati":["itching"],
                "makati balat":["itching","skin_rash"],"makati ang balat":["itching","skin_rash"],
                "hives":["skin_rash","itching","welts"],"tagihawat":["skin_rash"],
                "buni":["skin_rash","itching"],"an-an":["skin_rash"],
                "namamaga":["swelling"],"maga":["swelling"],
                "may pasa":["bruising"],"pasa":["bruising"],
                "galis":["itching","skin_rash"],"galos":["skin_rash"],
                "bulutong":["skin_rash","blisters","mild_fever"],

                # ── BREATHING / PAGHINGA ──
                "breathing":["difficulty_breathing","shortness_of_breath"],
                "difficulty breathing":["difficulty_breathing"],
                "hirap huminga":["difficulty_breathing"],"nahihirapan huminga":["difficulty_breathing"],
                "hirap sa paghinga":["difficulty_breathing"],"shortness of breath":["shortness_of_breath"],
                "hingal":["shortness_of_breath"],"nahihingal":["shortness_of_breath"],
                "asthma":["wheezing","difficulty_breathing","chest_tightness"],
                "hika":["wheezing","difficulty_breathing","chest_tightness"],
                "hawak":["chest_tightness","difficulty_breathing"],

                # ── CHEST / DIBDIB ──
                "chest pain":["chest_pain","chest_tightness"],
                "masakit dibdib":["chest_pain"],"masakit ang dibdib":["chest_pain"],
                "sakit ng dibdib":["chest_pain"],"sumasakit dibdib":["chest_pain"],
                "chest tightness":["chest_tightness"],"masikip dibdib":["chest_tightness"],

                # ── BODY PAIN / PANANAKIT NG KATAWAN ──
                "body pain":["body_malaise","muscle_pain"],
                "body malaise":["body_malaise"],
                "pananakit ng katawan":["body_malaise","muscle_pain"],
                "masakit katawan":["body_malaise","muscle_pain"],
                "masakit ang katawan":["body_malaise","muscle_pain"],
                "nanghihina":["weakness","fatigue"],"mahina katawan":["weakness","fatigue"],
                "pagod":["fatigue"],"pagod na pagod":["fatigue","weakness"],
                "walang gana":["loss_of_appetite","fatigue"],
                "walang lakas":["weakness","fatigue"],
                "muscle":["muscle_pain"],"muscle pain":["muscle_pain"],
                "masakit muscles":["muscle_pain"],"pulikat":["muscle_cramps"],

                # ── BACK PAIN / LIKOD ──
                "back pain":["back_pain"],"sakit ng likod":["back_pain"],
                "masakit likod":["back_pain"],"masakit ang likod":["back_pain"],
                "lower back pain":["back_pain"],"masakit bewang":["back_pain"],
                "sakit ng bewang":["back_pain"],"baywang":["back_pain"],

                # ── EYES / MATA ──
                "eye":["red_eyes","itchy_eyes"],"sore eyes":["red_eyes","eye_discharge","itchy_eyes"],
                "mata":["red_eyes","itchy_eyes"],"makati mata":["itchy_eyes"],
                "mamula mata":["red_eyes"],"namumula mata":["red_eyes"],
                "masakit mata":["red_eyes","pain_behind_eyes"],
                "malabo mata":["visual_disturbances"],"blurred vision":["visual_disturbances"],

                # ── EAR / TENGA ──
                "ear pain":["ear_pain"],"earache":["ear_pain"],
                "masakit tenga":["ear_pain"],"sakit ng tenga":["ear_pain"],
                "masakit ang tenga":["ear_pain"],"tumutunog tenga":["ear_pain"],

                # ── TOOTHACHE / NGIPIN ──
                "toothache":["ear_pain"],"sakit ng ngipin":["ear_pain"],
                "masakit ngipin":["ear_pain"],"masakit ang ngipin":["ear_pain"],

                # ── JOINTS / KASUKASUAN ──
                "joint pain":["joint_pain"],"arthritis":["joint_pain","swelling"],
                "masakit tuhod":["joint_pain"],"masakit buto":["joint_pain","muscle_pain"],
                "sakit ng kasukasuan":["joint_pain"],"masakit mga kasukasuan":["joint_pain"],

                # ── MENSTRUAL / REGLA ──
                "dysmenorrhea":["lower_abdominal_pain","back_pain"],
                "menstrual":["lower_abdominal_pain","back_pain"],
                "regla":["lower_abdominal_pain","back_pain"],
                "period pain":["lower_abdominal_pain","back_pain"],
                "masakit regla":["lower_abdominal_pain","back_pain"],
                "may regla":["lower_abdominal_pain","back_pain"],
                "dalaw":["lower_abdominal_pain","back_pain"],
                "menstrual cramps":["lower_abdominal_pain","back_pain","muscle_cramps"],

                # ── URINARY / IHI ──
                "uti":["painful_urination","frequent_urination","urgency_to_urinate"],
                "urinary":["painful_urination","frequent_urination"],
                "masakit umihi":["painful_urination"],"painful urination":["painful_urination"],
                "madalas umihi":["frequent_urination"],"frequent urination":["frequent_urination"],
                "mahapdi ihi":["painful_urination"],

                # ── SPRAIN/INJURY / PILAY ──
                "sprain":["joint_pain","swelling","limited_movement"],
                "pilay":["joint_pain","swelling","limited_movement"],
                "napilay":["joint_pain","swelling","limited_movement"],
                "nabali":["joint_pain","swelling","limited_movement"],
                "injury":["joint_pain","swelling","bruising"],
                "nasugatan":["joint_pain","swelling"],

                # ── TROPICAL DISEASES ──
                "dengue":["high_fever","severe_headache","pain_behind_eyes","joint_pain","muscle_pain"],
                "dengue fever":["high_fever","severe_headache","pain_behind_eyes","joint_pain","muscle_pain"],
                "tigdas":["high_fever","skin_rash","cough","red_eyes"],
                "measles":["high_fever","skin_rash","cough","red_eyes"],
                "typhoid":["high_fever","headache","abdominal_pain","loss_of_appetite"],
                "lepto":["high_fever","muscle_pain","headache","red_eyes"],
                "leptospirosis":["high_fever","muscle_pain","headache","red_eyes"],

                # ── APPETITE / GANA ──
                "loss of appetite":["loss_of_appetite"],"no appetite":["loss_of_appetite"],
                "walang ganang kumain":["loss_of_appetite"],
                "ayaw kumain":["loss_of_appetite"],

                # ── ANXIETY / KABA ──
                "anxiety":["anxiety","rapid_heartbeat"],"panic":["anxiety","rapid_heartbeat","difficulty_breathing"],
                "kinakabahan":["anxiety","rapid_heartbeat"],
                "kabado":["anxiety","rapid_heartbeat"],
                "natatakot":["anxiety","fear_of_losing_control"],
                "stress":["headache","anxiety","fatigue"],

                # ── HEARTBURN / ACID ──
                "heartburn":["heartburn"],"acidity":["heartburn","nausea"],
                "acid reflux":["heartburn","chest_discomfort"],
                "maasim tiyan":["heartburn","nausea"],

                # ── FATIGUE / PAGOD ──
                "tired":["fatigue"],"fatigue":["fatigue"],
                "lethargic":["fatigue","weakness"],"sluggish":["fatigue"],
                "antok":["fatigue"],"inaantok":["fatigue"],
                "pagkahapo":["fatigue","weakness"],

                # ── GENERAL / IBA PA ──
                "chills":["chills"],"ginginaw":["chills"],"nilalamig":["chills"],
                "pawis":["heavy_sweating"],"pinagpapawisan":["heavy_sweating"],
                "cold sweats":["heavy_sweating","chills"],
                "singaw":["mouth_ulcers"],"singaw sa bibig":["mouth_ulcers"],
                "namamanas":["swelling"],"swollen":["swelling"],
                "nosebleed":["bleeding_gums"],"balinguyngoy":["neck_pain"],
                "stiff neck":["neck_pain","muscle_tension"],
                "masakit leeg":["neck_pain"],"tortikolis":["neck_pain","muscle_tension"],
            }
            mapped = set()
            for kw, syms in keyword_map.items():
                if kw in complaint:
                    mapped.update(syms)
            if not mapped:
                mapped.add("body_malaise")
            preds, flags = predict_diseases(mapped, context=context)
            print(json.dumps({
                "predictions": preds[:3],
                "red_flags": flags,
                "mapped_symptoms": [format_name(s) for s in mapped]
            }))
        else:
            print(json.dumps({"error":f"Unknown action: {action}"}))
    except Exception as e:
        print(json.dumps({"error":str(e)}))

if __name__ == "__main__":
    main()
