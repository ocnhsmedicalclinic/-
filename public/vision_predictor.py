import sys
import json
import os
import cv2
import numpy as np
from PIL import Image

def analyze_rash(img_path):
    """
    Analyzes a skin rash image for color distribution and texture.
    """
    if not os.path.exists(img_path):
        return {"error": "Image file not found"}

    try:
        # Load image
        img = cv2.imread(img_path)
        if img is None:
            return {"error": "Failed to decode image"}

        hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)

        # Redness detection (Common in rashes)
        # Range 1
        lower_red1 = np.array([0, 50, 50])
        upper_red1 = np.array([10, 255, 255])
        mask1 = cv2.inRange(hsv, lower_red1, upper_red1)

        # Range 2
        lower_red2 = np.array([170, 50, 50])
        upper_red2 = np.array([180, 255, 255])
        mask2 = cv2.inRange(hsv, lower_red2, upper_red2)

        red_mask = mask1 + mask2
        red_percentage = (np.count_nonzero(red_mask) / red_mask.size) * 100

        # Pattern detection (Texture)
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        circles = cv2.HoughCircles(gray, cv2.HOUGH_GRADIENT, 1, 20, param1=50, param2=30, minRadius=5, maxRadius=50)
        
        circle_count = len(circles[0]) if circles is not None else 0

        # Heuristic Diagnosis Support
        findings = []
        possible_conditions = []

        if red_percentage > 5:
            findings.append(f"Significant erythema detected ({round(red_percentage, 1)}% of area).")
            possible_conditions.append("Contact Dermatitis")
            possible_conditions.append("Urticaria")
        
        if circle_count > 5:
            findings.append(f"Bulla/Vesicle-like patterns detected ({circle_count} clusters).")
            possible_conditions.append("Chickenpox")
            possible_conditions.append("Hand Foot Mouth Disease")

        return {
            "type": "Rash Analysis",
            "findings": findings,
            "redness_index": round(red_percentage, 2),
            "pattern_score": circle_count,
            "suggested_focus": list(set(possible_conditions))
        }
    except Exception as e:
        return {"error": str(e)}

def analyze_xray(img_path):
    """
    Analyzes an X-ray for opacity and structural patterns.
    """
    try:
        img = cv2.imread(img_path, cv2.IMREAD_GRAYSCALE)
        if img is None:
            return {"error": "Failed to decode X-ray"}

        # Basic Opacity Check (Pneumonia Detection Aid)
        # Apply threshold to find high-density areas relative to lungs
        _, thresh = cv2.threshold(img, 200, 255, cv2.THRESH_BINARY)
        opacity_score = (np.count_nonzero(thresh) / thresh.size) * 100

        findings = []
        if opacity_score > 15:
            findings.append("Detected areas of high radio-opacity. Possible infiltration.")
        
        return {
            "type": "X-ray Analysis",
            "findings": findings,
            "opacity_index": round(opacity_score, 2),
            "suggested_focus": ["Pneumonia", "Pleural Effusion"] if opacity_score > 10 else ["Normal"]
        }
    except Exception as e:
        return {"error": str(e)}

def main():
    try:
        data = json.loads(sys.stdin.read() or '{}')
        img_path = data.get("image_path")
        mode = data.get("mode", "rash") # rash or xray

        if not img_path:
            print(json.dumps({"error": "No image path provided"}))
            return

        if mode == "rash":
            result = analyze_rash(img_path)
        elif mode == "xray":
            result = analyze_xray(img_path)
        else:
            result = {"error": "Invalid mode"}

        result["engine"] = "AI Vision v1.0 (OpenCV)"
        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    main()
