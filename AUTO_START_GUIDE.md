# How to Auto-Start the System (Run without opening XAMPP)

Yes, you can make the system run automatically when your computer turns on, so you **don't need to open the XAMPP Control Panel every time**.

Here is how to set it up:

## Step 1: Open XAMPP as Administrator
**IMPORTANT:** You must run XAMPP as **Administrator** for this to work.

1.  Close XAMPP if it is currently open.
2.  Click the **Start Menu** and search for **XAMPP Control Panel**.
3.  **Right-click** on it and select **"Run as administrator"**.
4.  Click **Yes** if Windows asks for permission.

## Step 2: Install Services
Look at the left side of the XAMPP Control Panel, next to the module names (Apache, MySQL).

1.  Find the **Red "X"** mark to the left of **Apache**.
2.  **Click the Red "X"**.
3.  A dialog box will appear asking: *"Click Yes to install the Apache service"*.
4.  Click **Yes**.
5.  Wait a moment. The Red "X" should turn into a **Green Checkmark (✔)**.

Repeat for MySQL:
1.  Find the **Red "X"** mark to the left of **MySQL**.
2.  **Click the Red "X"**.
3.  Click **Yes** to install the MySQL service.
4.  Wait for the **Green Checkmark (✔)**.

## Step 3: Verify
Now that both have Green Checkmarks, they are installed as **Windows Services**.

-   **What this means:** Windows will automatically start Apache (Web Server) and MySQL (Database) in the background as soon as the computer boots up.
-   **Test:** You can now restart your computer. When it turns back on, try accessing your system (`http://localhost/clinic-system/public` or your custom domain) **without opening XAMPP**. It should work immediately!

---

## How to Stop/Uninstall (If needed)
If you ever want to stop them from running automatically:
1.  Open XAMPP as Administrator.
2.  Click the **Green Checkmark (✔)**.
3.  Click **Yes** to uninstall the service.
4.  It will turn back into a Red "X".
