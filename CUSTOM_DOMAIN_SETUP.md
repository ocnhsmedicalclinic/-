# How to Create a Custom URL (Intranet)

You want your clinic system to be accessible via a custom domain (like `http://ocnhs.local` or `http://ocnhs.clinic`) instead of an IP address (like `192.168.1.15`).

Since this is a **Local Intranet** (no internet), you cannot just register a `.com` domain. You must tell each computer on your network how to find your server.

---

## Option 1: The "Hosts File" Method (Best for 1-5 PCs)
You must do this on **EVERY Computer** that needs to access the system.

### Step 1: Find Server IP
1.  On the **Server Computer**, open Command Prompt (`cmd`).
2.  Type `ipconfig`.
3.  Note the **IPv4 Address** (e.g., `192.168.1.15`).

### Step 2: Edit Hosts File on Other PCs
On each **Admin/Doctor/Nurse PC**:
1.  Open **Notepad** as Administrator (Right-click Notepad -> Run as Administrator).
2.  Click **File > Open**.
3.  Navigate to: `C:\Windows\System32\drivers\etc\`
4.  Change the file type dropdown from "Text Documents (*.txt)" to **"All Files (*.*)"**.
5.  Select and open the file named **`hosts`**.
6.  Add this line at the very bottom:
    ```
    192.168.1.15       ocnhs.medicalclinic
    192.168.1.15       ocnhs.local
    ```
    *(Replace `192.168.1.15` with your actual Server IP)*
7.  Save and Close.

### Step 3: Access
Now, on those PCs, you can open Chrome/Edge and type:
**`http://ocnhs.medicalclinic`**

---

## Option 2: The "Router DNS" Method (Best for Many Devices + Phones)
If your office router supports "Local DNS" or "Static DNS" entries (common in business routers, rare in home routers):
1.  Log in to your Router Admin Panel (usually `192.168.1.1`).
2.  Find **DNS Settings** or **Local Domain Name**.
3.  Add an entry:
    - **Domain Name**: `ocnhs.medicalclinic`
    - **IP Address**: `192.168.1.15` (Server IP)
4.  Save.
   
This will make `http://ocnhs.medicalclinic` work on **ALL** devices (PCs, iPhones, Androids) connected to that WiFi automatically!

---

## Troubleshooting
**"This site can’t be reached"**
- Double-check the Server IP address. Did it change? (See "Static IP" in DEPLOYMENT_GUIDE.md)
- Ensure Apache is running in XAMPP on the server.
- Ensure Windows Firewall on the server allows "Apache HTTP Server".

---

## See Also
-   [**Make System Start Automatically**](AUTO_START_GUIDE.md) (Use without opening XAMPP)

