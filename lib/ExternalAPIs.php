<?php

class ExternalAPIs
{

    /**
     * Search for medicines using OpenFDA API
     * Returns a list of brand names and generic names
     */
    public function searchMedicine($query)
    {
        if (empty($query))
            return [];

        $query = urlencode($query);
        // OpenFDA API endpoint for drug labels
        $url = "https://api.fda.gov/drug/label.json?search=openfda.brand_name:\"$query\"+openfda.generic_name:\"$query\"&limit=5";

        // Use curl for better control (or file_get_contents if allowed)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
        curl_setopt($ch, CURLOPT_USERAGENT, 'OCNHS-Medical-Clinic/1.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $results = [];

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['results'])) {
                foreach ($data['results'] as $item) {
                    $brand = $item['openfda']['brand_name'][0] ?? '';
                    $generic = $item['openfda']['generic_name'][0] ?? '';

                    if ($brand && $generic) {
                        $results[] = "$brand ($generic)";
                    } elseif ($brand) {
                        $results[] = $brand;
                    } elseif ($generic) {
                        $results[] = $generic;
                    }
                }
            }
        }

        // Fallback/Mock if API fails or returns nothing (for demo reliability)
        if (empty($results)) {
            // Mock data for common PH meds if API is empty (common in dev environments without internet)
            $mockMeds = [
                'Biogesic (Paracetamol)',
                'Neozep (Phenylephrine HCl + Chlorphenamine Maleate + Paracetamol)',
                'Bioflu (Phenylephrine HCl + Chlorphenamine Maleate + Paracetamol)',
                'Alaxan (Ibuprofen + Paracetamol)',
                'Solmux (Carbocisteine)',
                'Amoxicillin',
                'Mefenamic Acid'
            ];
            $q = urldecode($query);
            foreach ($mockMeds as $med) {
                if (stripos($med, $q) !== false) {
                    $results[] = $med;
                }
            }
        }

        return array_unique(array_slice($results, 0, 5));
    }

    /**
     * Get simulated DOH Epidemic alerts
     * Since actual DOH API is not open/real-time, we simulate alerts based on seasonality or fetch strictly if available.
     */
    public function getDOHAlerts()
    {
        // Month-based seasonality alerts (Simulated DOH Data)
        $month = date('n');
        $alerts = [];

        // Dengue Season (June - October)
        if ($month >= 6 && $month <= 10) {
            $alerts[] = [
                'disease' => 'Dengue',
                'level' => 'High Alert',
                'description' => 'DOH reports rising Dengue cases in Region III. Monitor students for high fever and rashes.'
            ];
        }

        // Flu Season (July - December, also Jan/Feb)
        if ($month >= 7 || $month <= 2) {
            $alerts[] = [
                'disease' => 'Influenza-like Illness',
                'level' => 'Moderate',
                'description' => 'Seasonal flu cases rising nationwide. Advise rest and hydration.'
            ];
        }

        // Summer: Heat Stroke (March - May)
        if ($month >= 3 && $month <= 5) {
            $alerts[] = [
                'disease' => 'Heat Stroke',
                'level' => 'Warning',
                'description' => 'Heat index reaching dangerous levels. Ensure students stay hydrated.'
            ];
        }

        // Generic Year-round
        if (empty($alerts)) {
            $alerts[] = [
                'disease' => 'General Monitoring',
                'level' => 'Low',
                'description' => 'No major outbreaks reported by DOH Region III.'
            ];
        }

        return $alerts;
    }
}
