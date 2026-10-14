# MangoCare Admin Management System

A comprehensive web-based admin management system for monitoring and managing mango leaf disease detection data from Android applications. Built with PHP and Firebase for real-time data synchronization.

## Features

### 📊 Dashboard
- Real-time statistics (total scans, trees, diseases, schedules)
- Visual charts for disease distribution and location-based scans
- Recent scans overview

### 🔍 Scan History
- View all mango leaf disease scans
- Filter by disease type, location, or tree ID
- Detailed scan information with timestamps and confidence levels

### 🌳 Mango Trees Management
- Register and manage mango trees
- Track scan history per tree
- Monitor spray status for each tree
- View detailed tree information

### 📅 Spray Scheduling
- Create and manage spray schedules
- Assign specific dates and times for spraying
- Monitor completed and pending schedules
- Prevent double spraying through QR code verification

### 📱 QR Code Generator
- Generate unique QR codes for each mango tree
- Download and print QR codes
- Track which trees have QR codes assigned
- QR codes contain tree ID and location information

### 📈 Reports & Analytics
- Comprehensive disease analysis
- Location-based statistics
- Spray schedule status overview
- Recent activity tracking

## Requirements

- PHP 7.4 or higher
- XAMPP (Apache + PHP + MySQL)
- Firebase Realtime Database account
- cURL extension enabled in PHP
- Modern web browser

## Installation

### 1. Clone or Download the Project

Place the project files in your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\mangocareadmin
```

### 2. Connect Firebase via google-services.json

The admin panel uses the **same Firebase config as your Android app**:

1. In [Firebase Console](https://console.firebase.google.com/) → your project → **Project settings**.
2. Under **Your apps**, select your Android app (`com.example.finalcapstone`).
3. Download **google-services.json** (or use the one you already have).
4. Place `google-services.json` in the project root:
   ```
   C:\xampp\htdocs\mangocareadmin\google-services.json
   ```
5. Ensure **Realtime Database** is enabled for the same project.

No manual editing of Firebase values in `config.php` is needed; they are read from `google-services.json`.

### 3. Optional: Database secret

To use authenticated access (recommended for production), add your **Database secret** in `config.php`:

- Firebase Console → Project settings → **Service accounts** → **Database secrets** → copy the secret.
- In `config.php`, set: `define('FIREBASE_DATABASE_SECRET', 'your-secret-here');`
- If you leave it empty, the app uses **public** read/write (only for development; ensure Realtime Database rules allow it).

**Important:** For production use, you should:
- Use Firebase Admin SDK with service account credentials
- Implement proper authentication
- Use Firebase Security Rules

### 4. Set Up Firebase Database Rules

In Firebase Console, go to Realtime Database > Rules and set:

```json
{
  "rules": {
    ".read": true,
    ".write": true
  }
}
```

**Note:** These rules allow public read/write access. For production, implement proper security rules based on authentication.

### 5. Configure Admin Credentials

Edit `config.php` to change the default admin credentials:

```php
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // Change this!
```

### 6. Start XAMPP

1. Start Apache from XAMPP Control Panel
2. Open your browser and navigate to:
   ```
   http://localhost/mangocareadmin
   ```

## Default Login Credentials

- **Username:** `admin`
- **Password:** `admin123`

**⚠️ IMPORTANT:** Change these credentials immediately after installation!

## Firebase Database Structure

The system uses the following Firebase database structure:

```
{
  "scans": {
    "scan_id": {
      "tree_id": "TREE-001",
      "disease": "Anthracnose",
      "location": "Farm A, Block 1",
      "timestamp": 1234567890000,
      "confidence": 95.5,
      "image_url": "https://..."
    }
  },
  "trees": {
    "TREE-001": {
      "tree_id": "TREE-001",
      "location": "Farm A, Block 1",
      "has_qr": true,
      "created_at": 1234567890000
    }
  },
  "spray_schedules": {
    "schedule_id": {
      "tree_id": "TREE-001",
      "location": "Farm A, Block 1",
      "spray_date": "2024-01-15",
      "spray_time": "10:00",
      "status": "pending",
      "created_at": 1234567890000,
      "created_by": "admin"
    }
  },
  "spray_history": {
    "spray_id": {
      "tree_id": "TREE-001",
      "timestamp": 1234567890000,
      "status": "completed",
      "notes": "Sprayed successfully"
    }
  }
}
```

## Android App Integration

The Android app should send scan data to Firebase in the following format:

```json
{
  "tree_id": "TREE-001",
  "disease": "Anthracnose",
  "location": "Farm A, Block 1",
  "timestamp": 1234567890000,
  "confidence": 95.5,
  "image_url": "https://firebasestorage.googleapis.com/..."
}
```

When a user scans a QR code before spraying, the app should:
1. Read the QR code data (tree_id, location)
2. Verify the tree hasn't been sprayed recently
3. Record the spray in `spray_history` collection
4. Update the spray schedule status if applicable

## Features Overview

### Dashboard
- Real-time statistics display
- Visual charts and graphs
- Quick overview of recent activity

### Scan Management
- View all disease scans
- Filter by multiple criteria
- Export capabilities (can be added)

### Tree Management
- Register trees with QR codes
- Track individual tree history
- Monitor spray status

### Spray Scheduling
- Create schedules with dates and times
- Assign to specific trees
- Track completion status

### QR Code System
- Generate unique QR codes per tree
- Prevent double spraying
- Easy download and printing

## Security Considerations

1. **Change default admin credentials** immediately
2. **Implement Firebase Security Rules** for production
3. **Use HTTPS** in production
4. **Implement session timeout** (already included)
5. **Validate all user inputs** (basic validation included)
6. **Use Firebase Admin SDK** for server-side operations in production

## Troubleshooting

### Firebase Connection Issues
- Verify your Firebase configuration in `config.php`
- Check if cURL is enabled: `php -m | grep curl`
- Ensure Firebase Realtime Database is enabled

### Session Issues
- Check PHP session configuration
- Ensure `session_start()` is called before any output

### QR Code Not Generating
- Check internet connection (uses external API)
- Verify tree ID and location are provided

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (responsive design)

## Responsive Design

The system is fully responsive and works on:
- Desktop computers
- Tablets
- Mobile phones

## Future Enhancements

Potential features to add:
- Export reports to PDF/Excel
- Email notifications for schedules
- Advanced analytics and predictions
- Multi-admin support
- Role-based access control
- Image upload and storage
- Real-time notifications

## Support

For issues or questions:
1. Check the troubleshooting section
2. Verify Firebase configuration
3. Check PHP error logs in XAMPP

## License

This project is developed for educational/capstone purposes.

## Author

Developed as a Capstone Project for Mango Leaf Disease Detection System.

---

**Note:** This is a development version. For production deployment, implement additional security measures, error handling, and performance optimizations.
