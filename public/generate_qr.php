<?php
require_once "../config/db.php";
// Ensure only admins can access this tool
requireAdmin();
// Apply global security headers
setSecurityHeaders();

// Construction of the public registration URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$ip = "192.168.0.101";
// Dynamically mimic the current URL path but point to public_register.php
$public_url = $protocol . "://" . $ip . str_replace('generate_qr.php', 'public_register.php', $_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration QR Code - Clinic System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00ACB1;
            --secondary: #607d8b;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .qr-card {
            background: white;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 30px auto;
            text-align: center;
            transition: all 0.3s ease;
        }

        #qrcode-container {
            position: relative;
            display: inline-block;
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 16px;
        }

        #final-canvas {
            display: block;
            max-width: 100%;
            height: auto;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        #final-canvas:hover {
            transform: scale(1.02);
        }

        .qr-info h1 {
            color: #333;
            font-size: 1.6rem;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .qr-info p {
            color: #666;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 25px 0;
            flex-wrap: wrap;
        }

        .action-btn {
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            font-size: 0.9rem;
            min-width: 130px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            color: white;
        }

        .btn-back {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }

        .btn-print {
            background: linear-gradient(135deg, #00ACB1 0%, #00d4aa 100%);
        }

        .btn-download {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            filter: brightness(1.1);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .action-btn:active {
            transform: translateY(-1px);
        }

        .url-box {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 10px;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 0.8rem;
            word-break: break-all;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
            color: #495057;
        }

        /* Dark Mode Overrides */
        body.dark-mode {
            background-color: #18191a;
        }

        body.dark-mode .qr-card {
            background: #242526 !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important;
            border: 1px solid #3a3b3c;
        }

        body.dark-mode .qr-info h1 {
            color: #e4e6eb;
        }

        body.dark-mode .qr-info p {
            color: #b0b3b8;
        }

        body.dark-mode .url-box {
            background: #3a3b3c;
            border-color: #4e4f50;
            color: #e4e6eb;
        }

        @media (max-width: 480px) {
            .qr-card {
                padding: 25px 15px;
                margin: 20px 10px;
            }

            .qr-info h1 {
                font-size: 1.3rem;
            }

            .btn-group {
                flex-direction: column;
                width: 100%;
            }

            .action-btn {
                width: 100%;
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
            }

            .qr-card {
                box-shadow: none;
                margin: 0 auto;
                padding: 0;
                width: 100%;
                border: none;
            }

            #qrcode-container {
                border: none;
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <!-- Main Content -->
        <div class="qr-card">
            <div class="qr-info">
                <h1>Registration QR Code</h1>
                <p>Scan to register student information.</p>
                <div class="url-box"><?php echo $public_url; ?></div>
            </div>

            <div id="qrcode-container" title="Click to open registration form">
                <canvas id="final-canvas" onclick="window.open(publicUrl, '_blank')"></canvas>
                <div id="temp-qr" style="display:none"></div>
            </div>

            <div class="no-print">
                <div class="btn-group">
                    <a href="student.php" class="action-btn btn-back">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button onclick="window.print()" class="action-btn btn-print">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button onclick="downloadQR()" class="action-btn btn-download">
                        <i class="fas fa-download"></i> PNG
                    </button>
                </div>
            </div>

            <p class="mt-4 text-muted small" style="font-style: italic;">High-resolution QR with School Logo</p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // Apply dark mode from localStorage immediately
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }

        const publicUrl = "<?php echo $public_url; ?>";
        const logoSrc = "assets/img/ocnhs_logo.png";

        function generateQR() {
            const container = document.getElementById("temp-qr");
            const finalCanvas = document.getElementById("final-canvas");
            const ctx = finalCanvas.getContext("2d");

            container.innerHTML = "";
            const qrcode = new QRCode(container, {
                text: publicUrl,
                width: 1000,
                height: 1000,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });

            setTimeout(() => {
                const qrImg = container.querySelector('img');
                const canvasSize = 1000;
                finalCanvas.width = canvasSize;
                finalCanvas.height = canvasSize;

                ctx.drawImage(qrImg, 0, 0, canvasSize, canvasSize);

                const logo = new Image();
                logo.src = logoSrc;
                logo.onload = () => {
                    const logoSize = canvasSize * 0.22;
                    const x = (canvasSize - logoSize) / 2;
                    const y = (canvasSize - logoSize) / 2;

                    ctx.fillStyle = "#ffffff";
                    const padding = 15;

                    function roundedRect(ctx, x, y, width, height, radius) {
                        ctx.beginPath();
                        ctx.moveTo(x + radius, y);
                        ctx.lineTo(x + width - radius, y);
                        ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
                        ctx.lineTo(x + width, y + height - radius);
                        ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
                        ctx.lineTo(x + radius, y + height);
                        ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
                        ctx.lineTo(x, y + radius);
                        ctx.quadraticCurveTo(x, y, x + radius, y);
                        ctx.closePath();
                    }

                    roundedRect(ctx, x - padding, y - padding, logoSize + (padding * 2), logoSize + (padding * 2), 20);
                    ctx.fill();
                    ctx.drawImage(logo, x, y, logoSize, logoSize);
                };
            }, 500);
        }

        function downloadQR() {
            const canvas = document.getElementById("final-canvas");
            const link = document.createElement('a');
            link.download = 'Student_Registration_QR.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }

        window.onload = generateQR;
    </script>
</body>

</html>