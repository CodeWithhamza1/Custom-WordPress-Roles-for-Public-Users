# Custom WordPress Roles Plugin

This plugin provides a **custom WordPress user role** called `nf_viewer`.  
The role is designed specifically for **viewing form submissions only**, with built-in **security checks** to prevent access to admin-level functionality. It ensures a safe and controlled way to allow users to view form submissions without exposing other parts of the WordPress dashboard.

---

## ✨ Features
- Adds a single custom role: **Form Submissions Viewer** (`nf_viewer`).
- Restricts access strictly to **form submissions only**.
- Prevents unauthorized access to admin menus, plugins, settings, and other sensitive areas.
- Lightweight, secure, and follows WordPress role management best practices.

---

## 📦 Installation
1. Download or clone this repository into your WordPress `wp-content/plugins` directory:
   ```bash
   git clone https://github.com/your-username/custom-wordpress-roles.git
   ```
2. Activate the plugin from the **WordPress Admin > Plugins** page.
3. The role `nf_viewer` will now be available to assign to users.

---

## 🔑 Available Custom Role

* **Form Submissions Viewer** (`nf_viewer`)

This role comes with carefully restricted **capabilities**, ensuring the assigned user can only **view form submissions** and nothing else.

---

## 🖥️ Usage

### For Admins:
* Go to **Users > All Users** in the WordPress dashboard.
* Add or edit a user.
* From the **Role dropdown**, select **Form Submissions Viewer (nf_viewer)**.
* Save changes.

Admins retain **full access** and remain unaffected.

### For Users Assigned `nf_viewer`:
* Can log in with their credentials.
* Will only see and access **form submissions**.
* Restricted from plugins, themes, settings, and other admin-only areas.

### Important Thing:
* It is only available for the `Ninja Forms` only and need to update the code to use through any other plugin or any other roles you want. This is just a robust idea to do a thing like a custom role.

---

## ⚙️ Security
* Built-in **capability checks** ensure the `nf_viewer` role cannot escalate privileges.
* Strictly follows the **principle of least privilege**.
* Prevents users from bypassing restrictions to sensitive admin-only pages.

---

## 📖 Example Code Snippet

To remove the role if it is no longer needed, add this snippet to your `functions.php`:

```php
function remove_nf_viewer_role() {
    if ( get_role('nf_viewer') ) {
        remove_role('nf_viewer');
    }
}
add_action('init', 'remove_nf_viewer_role');
```

---

## 🚀 Contributing
Contributions are welcome! Fork the repo, make improvements, and submit a pull request.

---

## 📜 License
This project is licensed under the **MIT License** – see the [LICENSE](LICENSE) file for details.
