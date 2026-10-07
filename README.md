# FoodSafe Track – Food Safety & Expiry Management System

FoodSafe Track is a web-based Food Safety and Expiry Management System designed to help restaurants, hotels, cafeterias, supermarkets, and food storage facilities manage food inventory, monitor expiry dates, track wastage, and maintain supplier information.

## 📌 Project Overview

FoodSafe Track provides a centralized platform for managing food items and reducing food wastage through expiry monitoring and inventory tracking.

### Main Workflow

Food Added → Inventory Tracking → Expiry Monitoring → Alerts → Expired Food Detection → Wastage Recording → Reports

## ✨ Features

- 🔐 Secure Login and Authentication
- 📊 Dashboard with inventory statistics
- 🍱 Food Inventory Management
- ➕ Add Food Items
- ✏️ Edit Food Items
- 🗑️ Delete Food Items
- 🔎 Search and Filter Inventory
- ⏰ Expiry Date Tracking
- ⚠️ Near-Expiry Alerts
- ❌ Expired Food Detection
- 🗑️ Food Wastage Management
- 🚚 Supplier Management
- 📈 Reports and Statistics
- 📄 CSV Export
- 🖨️ Print Reports
- 📱 Responsive Design
- 🔒 Password Hashing and Session Authentication
- 🗄️ MySQL Database Integration

## 🧩 Modules

1. **Dashboard**
   - Total food items
   - Fresh items
   - Near-expiry items
   - Expired items
   - Wastage statistics

2. **Inventory Management**
   - Add, edit and delete food items
   - Search and filter food inventory
   - Track quantity and expiry dates

3. **Expiry Tracker**
   - Fresh food
   - Near-expiry food
   - Critical items
   - Expired food

4. **Wastage Management**
   - Record wasted food
   - Track wastage quantity
   - Record wastage reasons

5. **Supplier Management**
   - Add suppliers
   - Edit supplier details
   - Delete suppliers
   - Manage supplier contact information

6. **Reports**
   - Inventory reports
   - Expiry reports
   - Wastage reports
   - CSV export
   - Print reports

## 🛠️ Technologies Used

- HTML5
- CSS3
- JavaScript
- PHP 8+
- MySQL
- Bootstrap 5
- Font Awesome
- Chart.js
- XAMPP
- Apache

## 📂 Project Structure

```text
FoodSafe-Track/
│
├── index.php
├── login.php
├── logout.php
├── dashboard.php
├── inventory.php
├── add-food.php
├── edit-food.php
├── delete-food.php
├── food-details.php
├── expiry-tracker.php
├── wastage.php
├── add-wastage.php
├── delete-wastage.php
├── suppliers.php
├── add-supplier.php
├── edit-supplier.php
├── delete-supplier.php
├── reports.php
├── settings.php
│
├── config/
│   └── database.php
│
├── includes/
│   ├── auth.php
│   ├── header.php
│   ├── navbar.php
│   ├── sidebar.php
│   └── footer.php
│
├── css/
│   ├── style.css
│   └── responsive.css
│
├── js/
│   └── app.js
│
├── assets/
│   ├── images/
│   └── icons/
│
└── database/
    └── foodsafe_track.sql
