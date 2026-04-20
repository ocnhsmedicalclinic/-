import sys
import json
import math
from datetime import datetime, timedelta

def calculate_linear_regression(x_values, y_values):
    n = len(x_values)
    if n < 2:
        return 0, 0, 0 # Not enough data

    sum_x = sum(x_values)
    sum_y = sum(y_values)
    sum_xy = sum(x * y for x, y in zip(x_values, y_values))
    sum_x2 = sum(x * x for x in x_values)

    denominator = n * sum_x2 - sum_x ** 2
    if denominator == 0:
        return 0, 0, 0

    slope = (n * sum_xy - sum_x * sum_y) / denominator
    intercept = (sum_y - slope * sum_x) / n

    # Calculate R-squared (Correlation Coefficient)
    try:
        y_mean = sum_y / n
        ss_tot = sum((y - y_mean) ** 2 for y in y_values)
        ss_res = sum((y - (slope * x + intercept)) ** 2 for x, y in zip(x_values, y_values))
        r_squared = 1 - (ss_res / ss_tot) if ss_tot != 0 else 0
    except:
        r_squared = 0

    return slope, intercept, r_squared

def main():
    try:
        # Read data from STDIN
        input_data = sys.stdin.read()
        if not input_data:
            print(json.dumps({"error": "No input data"}))
            return

        data = json.loads(input_data)
        # Expected format: { "2023-01-01": {"Headache": 2, "Fever": 1}, "2023-01-02": ... }

        if not data:
            print(json.dumps({"risk_level": "Low", "message": "Insufficient data (Python)."}))
            return

        # Transform data: Group by Ailment -> List of (DayIndex, Count)
        ailments_series = {}
        sorted_dates = sorted(data.keys())
        
        if len(sorted_dates) < 5:
             print(json.dumps({"risk_level": "Low", "message": "Need at least 5 days of data for AI analysis."}))
             return

        start_date = datetime.strptime(sorted_dates[0], "%Y-%m-%d")

        for date_str in sorted_dates:
            day_stats = data[date_str]
            current_date = datetime.strptime(date_str, "%Y-%m-%d")
            day_index = (current_date - start_date).days

            for ailment, count in day_stats.items():
                if ailment not in ailments_series:
                    ailments_series[ailment] = []
                # Fill zeros for missing days if sparse? 
                # For simplicity, we assume the PHP sends all dates, so zero counts are explicit or implied.
                # Actually, PHP should send zeros for days with no cases if we want accuracy.
                # But here we handle sparse data:
                ailments_series[ailment].append((day_index, count))

        # Analyze each ailment
        results = []
        for name, points in ailments_series.items():
            # If sparse, we might have missing days. 
            # Ideally we want a continuous series. 
            # Construct full series
            full_series_x = []
            full_series_y = []
            point_dict = {x: y for x, y in points}
            max_day = (datetime.strptime(sorted_dates[-1], "%Y-%m-%d") - start_date).days
            
            for i in range(max_day + 1):
                full_series_x.append(i)
                full_series_y.append(point_dict.get(i, 0))

            slope, intercept, r2 = calculate_linear_regression(full_series_x, full_series_y)
            
            # Prediction for next day
            next_day_index = max_day + 1
            prediction = slope * next_day_index + intercept
            
            results.append({
                "name": name,
                "slope": slope,
                "r2": r2,
                "current_total": sum(full_series_y),
                "prediction": max(0, prediction) # No negative prediction
            })

        # Determine Outbreak Risk
        # Alert if: Slope is Positive AND (High R2 OR High Volume)
        risk_level = "Low"
        alert_message = "No significant trends detected."
        top_risk_ailment = None

        # Sort by urgency (Slope * Volume)
        results.sort(key=lambda x: x['slope'] * math.log(x['current_total'] + 1), reverse=True)

        if results:
            top = results[0]
            if top['slope'] > 0.5: # Rising trend
                risk_level = "High" if top['current_total'] > 10 else "Moderate"
                alert_message = f"AI Warning: rapid increase in {top['name']} cases detected."
                top_risk_ailment = top['name']
            elif top['slope'] > 0.2:
                 risk_level = "Moderate"
                 alert_message = f"Notice: slight upward trend in {top['name']}."
                 top_risk_ailment = top['name']

        # Final Rationale
        rationale = "No significant trends found."
        if top_risk_ailment:
            if risk_level == "High":
                rationale = f"Ailment {top_risk_ailment} shows exponential growth potential."
            elif risk_level == "Moderate":
                rationale = f"Gradual rise in {top_risk_ailment} suggests early monitoring."

        output = {
            "risk_level": risk_level,
            "message": alert_message,
            "top_ailment": top_risk_ailment,
            "rationale_insight": rationale,
            "analysis_details": results[:5] 
        }

        print(json.dumps(output))

    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    main()
