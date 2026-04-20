# Deployment Guide: Clinic System

This guide explains how to move your Clinic System to a dedicated "Server Computer" for your Intranet (Local Network).

## Prerequisites on the Server Computer
1.  **Computer**: A dedicated PC or Laptop that will serve as the server. It should be turned on whenever the system is needed.
2.  **Software**: Install **XAMPP** (choose the version with PHP 8.0 or higher).
3.  **Network**: The server must be connected to the same WiFi/Network as the other computers.

---

## Step 1: Transfer Files
1.  On your current computer, copy the entire **`clinic-system`** folder from `C:\xampp\htdocs\`.
2.  On the **Server Computer**, paste it into `C:\xampp\htdocs\`.
   - Result: `C:\xampp\htdocs\clinic-system`

## Step 2: database Setup
1.  **Export**:
    - On your current computer, go to `http://localhost/phpmyadmin`.
    - Click on **`clinic_db`**.
    - Click **Export** > **Go**.
    - Save the `.sql` file.
2.  **Import**:
    - On the **Server Computer**, open XAMPP Control Panel and start **Apache** and **MySQL**.
    - Go to `http://localhost/phpmyadmin`.
    - Click **New** > Create database named **`clinic_db`**.
    - Click **Import** > Choose the `.sql` file > **Go**.

## Step 3: Configure Apache (Make it accessible)
On the **Server Computer**:
1.  Open the file: `C:\xampp\apache\conf\extra\httpd-vhosts.conf`.
2.  Add this configuration at the bottom:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs"
    ServerName localhost
    
    # Intranet Alias
    Alias /ocnhsmedicalclinic "C:/xampp/htdocs/clinic-system/public"
    Alias /assets "C:/xampp/htdocs/clinic-system/public/assets"
    Alias /uploads "C:/xampp/htdocs/clinic-system/public/uploads"

    <Directory "C:/xampp/htdocs/clinic-system/public">
        Options Indexes FollowSymLinks Includes ExecCGI
        AllowOverride All
        Require all granted
    </Directory>
    
    <Directory "C:/xampp/htdocs/clinic-system/public/assets">
        Options Indexes FollowSymLinks
        Require all granted
    </Directory>

    <Directory "C:/xampp/htdocs/clinic-system/public/uploads">
        Options Indexes FollowSymLinks
        Require all granted
    </Directory>
</VirtualHost>
```
3.  **Restart Apache** in XAMPP.

## Step 4: Allow Access Through Firewall
Windows Firewall might block other computers from connecting.
1.  On the Server Computer, search for **"Allow an app through Windows Firewall"**.
2.  Click **Change Settings**.
3.  Find **Apache HTTP Server** in the list.
4.  Check **BOTH** "Private" and "Public" checkboxes.
5.  Click **OK**.

## Step 5: Find the Server IP
1.  On the Server Computer, open Command Prompt (`cmd`).
2.  Type `ipconfig` and press Enter.
3.  Look for **IPv4 Address**. (Example: `192.168.1.15`)

## Step 6: Access from Other Devices
Go to any other computer or phone on the network and type:
`http://<SERVER-IP-ADDRESS>/ocnhsmedicalclinic`

Example: `http://192.168.1.15/ocnhsmedicalclinic`

---

## IMPORTANT: Static IP (Optional but Recommended)
If you restart your router, the Server IP might change (e.g., from `...1.15` to `...1.20`). To prevent this:
1.  Go to **Control Panel** > **Network and Sharing Center** > **Change adapter settings**.
2.  Right-click your WiFi/LAN adapter > **Properties**.
3.  Select **Internet Protocol Version 4 (TCP/IPv4)** > **Properties**.
4.  Select **"Use the following IP address"** and enter:
    - **IP Address**: The current IP (e.g., `192.168.1.15`)
    - **Subnet mask**: `255.255.255.0`
    - **Default gateway**: Copy from `ipconfig` output.
    - **DNS**: Use `8.8.8.8` (Google) or your Gateway IP.

---

## Step 7: Auto-Start System
To make the system run automatically when the computer turns on (without opening XAMPP manually), follow the guide:
[**How to Auto-Start System**](AUTO_START_GUIDE.md)

