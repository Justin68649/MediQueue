# MediQueue Notification System Documentation

## Overview
The notification system provides real-time notifications across all portals (Patient, Staff, and Admin) in MediQueue. Users receive notifications for important queue events such as being called, service completion, new queue entries, and more.

## Key Features

### 1. **Notification Bell Icon**
- Added to all three dashboards (Patient, Staff, Admin)
- Located in the navigation header
- Shows a red badge with unread notification count
- Displays notification dropdown on hover

### 2. **Notification Triggers**

#### For Patients:
- ✅ **Queue Joined**: When a patient successfully joins a queue
- ✅ **Patient Called**: When their queue number is called
- ✅ **Service Started**: When staff begins serving them
- ✅ **Service Completed**: When service is finished

#### For Staff:
- ✅ **New Queue Entry**: When a new patient joins their department queue
- ✅ **Service Started**: Notifications from admin about other staff members
- ✅ **Service Completed**: Notifications from admin about queue completions

#### For Admin:
- ✅ **All Events**: Notifications about patient calls, service starts, service completions
- ✅ **New Queue Entries**: When new patients join any department
- ✅ **Staff Activity**: Monitoring staff service activities

### 3. **Database Schema**

The `notifications` table contains:
```
- id: Primary key
- user_id: Foreign key to users table
- queue_entry_id: Reference to queue entry (optional)
- type: ENUM ('queue_called', 'queue_updated', 'reminder', 'alert', 'info')
- title: Notification title
- message: Notification content
- is_read: Boolean (default: FALSE)
- sent_via_sms: Boolean (default: FALSE)
- sent_via_email: Boolean (default: FALSE)
- sent_via_push: Boolean (default: TRUE - push notifications in UI)
- created_at: Timestamp
```

## API Endpoints

### 1. **Get Notifications**
```
GET /api/notifications/get_notifications.php
Parameters:
  - page: Page number (default: 1)
  - limit: Items per page (default: 10)
  - unread: Show only unread (1 or 0)

Response:
{
  "success": true,
  "notifications": [...],
  "total": 5,
  "page": 1,
  "limit": 10,
  "total_pages": 1
}
```

### 2. **Mark as Read**
```
POST /api/notifications/mark_read.php
Body:
{
  "notification_id": 123
  // OR
  "mark_all": true
}

Response:
{
  "success": true,
  "message": "Notification marked as read"
}
```

### 3. **Delete Notification**
```
POST /api/notifications/delete_notification.php
Body:
{
  "notification_id": 123
}

Response:
{
  "success": true,
  "message": "Notification deleted"
}
```

### 4. **Get Unread Count**
```
GET /api/notifications/unread_count.php

Response:
{
  "success": true,
  "unread_count": 3
}
```

### 5. **Create Notification (Internal)**
```
POST /api/notifications/create_notification.php
Body:
{
  "user_id": 1,
  "type": "info",
  "title": "Notification Title",
  "message": "Notification message",
  "queue_entry_id": 123 (optional),
  "sent_via_sms": false (optional),
  "sent_via_email": false (optional),
  "sent_via_push": true (optional)
}

Response:
{
  "success": true,
  "message": "Notification created",
  "notification_id": 456
}
```

## How It Works

### Frontend (JavaScript)
1. **Page Load**: `initNotifications()` is called
2. **Load Notifications**: `loadNotifications()` fetches unread notifications
3. **Display**: Notifications appear in dropdown with read/delete options
4. **Auto-Refresh**: Notifications refresh every 30 seconds
5. **Interactions**: Users can mark as read or delete notifications

### Backend (PHP)
1. **Trigger Events**: When queue status changes, notifications are created
2. **Database Storage**: All notifications stored in `notifications` table
3. **API Access**: Frontend fetches via authenticated API endpoints
4. **Auto-cleanup**: Old read notifications can be archived/deleted

## Usage Examples

### Creating a Notification from Code
```php
sendNotification(
    $userId,                    // User ID
    "Service Completed",        // Title
    "Your service is complete", // Message
    'info',                     // Type (optional)
    $queueEntryId              // Queue Entry ID (optional)
);
```

### Checking Unread Notifications
1. Open any dashboard
2. Look for the bell icon in the navigation
3. Red badge shows count of unread notifications
4. Hover over bell to see notification dropdown

## Notification Types

| Type | Use Case |
|------|----------|
| `queue_called` | Patient's queue number called |
| `queue_updated` | General queue updates |
| `reminder` | Reminders to patients |
| `alert` | Important alerts |
| `info` | General information |

## Testing

You can test the notification system by:

1. **Run Testing Script**
   ```
   php test_notifications.php
   ```
   This will verify:
   - Database table exists
   - Notifications can be created
   - API endpoints are accessible
   - Functions work correctly

2. **Manual Testing**
   - Login as a patient
   - Join a queue
   - Check if notification appears in bell dropdown
   - Mark notification as read
   - Delete notification

3. **Cross-Portal Testing**
   - Login as patient, staff, and admin
   - Verify each sees appropriate notifications
   - Check that admin sees all activities

## Features Implemented

✅ Notification icons with badge count
✅ Notification dropdown UI
✅ Real-time notification loading (30s refresh)
✅ Mark as read functionality
✅ Delete notification functionality
✅ Notification creation on queue events
✅ Staff notifications for new queue entries
✅ Admin notifications for all activities
✅ Persistent storage in database
✅ Responsive design for all screen sizes

## Notes

- Notifications are stored in the database indefinitely unless manually deleted
- The badge shows count of ALL unread notifications (not just 5)
- Notifications auto-refresh every 30 seconds
- Clicking on a notification marks it as read
- Delete button removes notification permanently
- "Mark all read" button marks all unread notifications as read at once

## Future Enhancements

Potential improvements:
- Email notifications (already configured in database)
- SMS notifications (already configured in database)
- Push browser notifications (Web Push API)
- Sound alerts for specific notification types
- Notification preferences/settings
- Notification history archive
- Bulk notification management
- Scheduled notifications/reminders