 OLONGAPO CITY NATIONAL HIGH SCHOOL  MEDICAL CLINIC MANAGEMENT SYSTEM
 COMPREHENSIVE PROJECT PROPOSAL & SYSTEM ARCHITECTURE (V2.0)



 1. Executive Summary
The Olongapo City National High School Medical Clinic Management System is a stateoftheart digital infrastructure designed to revolutionize schoolbased healthcare. In a largescale institution like OCNHS, manual paperbased recordkeeping is no longer sustainable. This system implements a LANbased, highperformance web application that centralizes medical data, automates complex health analytics, and integrates Artificial Intelligence to predict health trends. It ensures data integrity, rapid response during emergencies, and seamless compliance with DepEd’s rigorous health reporting standards.



 2. Project Description & Vital Objectives
The system serves as the centralized "Health Intelligence Hub" for all OCNHS students and personnel.

   Operational Efficiency: Reduce patient retrieval, consultation logging, and document generation time by over 90%.
   Precision Healthcare: Eliminate manual computation errors in BMI, Nutritional Status (WHO Standards), and HeightforAge metrics.
   Regulatory Compliance: Instant generation of standardized DepEd reports (Monthly Census, Daily Ailments, PE Monitoring).
   Data Sovereignty: A secure, intranetonly deployment that ensures sensitive health data never leaves the school premises.
   Intelligent Monitoring: Leverage Machine Learning to detect potential disease outbreaks before they escalate.



 3. Core Module Breakdown

    A. Centralized Patient Management
   Digital Health Cards: Comprehensive EMR (Electronic Medical Record) containing demographics, vaccination history, allergies, and emergency contacts.
   QR Code Integration: Unique QR codes for students and employees for instant record retrieval and touchless identification.
   Public Registration Portal: Selfservice registration kiosks for students and employees to maintain accurate personal data without administrative overhead.

   B. Automated Health Analytics
   WHOStandard Nutritional Tracking: Automatic calculation of BMI, Stunting (HeightforAge), and Wasting categories upon entry of vitals.
   Growth Monitoring: Historical tracking of physical development over a student's entire tenure at OCNHS.

   C. Clinical Operations & Document Suite
   Treatment Logs: Fastentry interface for logging complaints, interventions, and medicine dispensing.
   Professional Certificate Suite: Highfidelity generation of:
       Medical Certificates (Fit & Sick templates).
       Laboratory Requests (Standardized school clinic format).
       Prescription Pads (Professional layout with physician signatures).
   Parental Consent Management: Digital tracking of health service consents for every student.

   D. Intelligent Inventory Control
   Realtime Stock Tracking: Automated deduction of medicines from the inventory as they are dispensed.
   Expiry Alerts: Proactive monitoring of medicine shelf life to ensure patient safety.

   E. AI & Predictive Analytics
   Disease Predictor: Machine Learning engine that analyzes symptoms to suggest potential diagnoses.
   Outbreak Forecasting: Pythondriven statistical models that analyze clinic visit frequency to detect and alert for potential epidemic spikes.

   F. System Governance & Security
   RoleBased Access Control (RBAC): Strict permissions for Admin and Staff accounts.
   Audit Trails (Action Logs): Detailed logging of all user activities for forensic accountability.
   Disaster Recovery: Integrated Backup and Restore modules to prevent data loss during hardware failure.



 4. The "OCNHS HealthFlow" Lifecycle

1.  Profiling: Student registers via the Public Portal or Admin entry; baseline vitals are captured.
2.  Assessment: System autocalculates nutritional markers and flags stunting/malnutrition.
3.  Consultation: During a clinic visit, the nurse pulls the record via Name search or QR scan.
4.  Treatment: Log visit details; medicines are autodeducted from inventory; medical documents (Rx/Lab requests) are generated with one click.
5.  Surveillance: The AI engine scans the daily logs to identify emerging health trends.
6.  Reporting: Monthend reports are generated instantly, matching DepEd’s required formatting perfectly.



 5. Technical Architecture
   Platform: Responsive Web Application (Optimized for Desktop/Tablet).
   Core Stack: PHP 8.x, MariaDB, JavaScript (ES6+), CSS3.
   Intelligence Layer: Python (Scikitlearn / Pandas) for MLbased forecasting.
   Deployment: Local Area Network (Intranet/XAMPP).
   Printing Hub: Integrated PDF/Print engine for standardized document output.



 6. Conclusion
The OCNHS Medical Clinic Management System is a missioncritical tool that transforms the school clinic from a storage room of folders into a sophisticated health management center. By marrying advanced automation with intuitive design, it empowers school healthcare providers to work faster, smarter, and more effectively, ensuring the OCNHS community remains healthy, protected, and welldocumented.
