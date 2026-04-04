# Notification System - User Guide & Testing Instructions

## 🔍 Where to Find the Notification Icon

### Patient Portal
- **URL**: `http://localhost/MediQueue/patient/index.php`
- **Location**: Top right of navigation bar, next to "Profile" link
- **Icon**: 🔔 Bell icon
- **Hover**: Dropdown appears showing your notifications

### Staff Portal  
- **URL**: `http://localhost/MediQueue/staff/index.php`
- **Location**: Top right of navigation bar, after "Profile" link
- **Icon**: 🔔 Bell icon with red badge if unread
- **Hover**: Dropdown appears showing your notifications

### Admin Portal
- **URL**: `http://localhost/MediQueue/admin/index.php`
- **Location**: Top right of navigation bar, before "Logout" button
- **Icon**: 🔔 Bell icon with red badge
- **Hover**: Dropdown appears showing system notifications

## 📝 How to Test the Notification System

### Step 1: Verify Database Table
```bash
# Open your database tool (phpMyAdmin) and check:
- Database: mediqueue_db
- Table: notifications
- Should have columns: id, user_id, queue_entry_id, type, title, message, is_read, etc.
```

### Step 2: Test API Endpoints
Open your browser and navigate to:

**Test 1: Get Unread Count**
```
http://localhost/MediQueue/api/notifications/unread_count.php
```
Expected: `{"success":true,"unread_count":0}`

**Test 2: Verify API Files Exist**
```
- /api/notifications/get_notifications.php ✅
- /api/notifications/mark_read.php ✅
- /api/notifications/delete_notification.php ✅
- /api/notifications/unread_count.php ✅
- /api/notifications/create_notification.php ✅
```

### Step 3: Manual System Test

#### Test Queue Notification:
1. Login as Patient
2. Click "Join Queue" for any department
3. Check notification bell icon
4. Should see "Queue Joined" notification
5. Click to mark as read
6. Badge count should decrease

#### Test Staff Notification:
1. Login as Staff member
2. Have a patient join a queue in your department
3. Check notification bell
4. Should see "New Queue Entry" notification
5. Open the queue and call the patient
6. This also triggers admin notification

#### Test Admin Notification:
1. Login as Admin
2. Observe staff activities
3. When staff calls patient → see notification
4. When service starts → see notification  
5. When service completes → see notification
6. Bell shows ALL system activity

### Step 4: Run Automated Test
```bash
Navigate to: http://localhost/MediQueue/test_notifications.php
```

This will:
- ✅ Check notifications table exists
- ✅ Create test notification
- ✅ Retrieve notification
- ✅ Test mark as read
- ✅ Test unread count query
- ✅ Test sendNotification() function
- ✅ Verify all API endpoint files exist
- ✅ Clean up test data

## 🎯 User Scenarios

### Scenario 1: Patient Gets Called
1. Patient waits in queue
2. **Notification**: "Your number XXX has been called"
3. Patient clicks notification → marks as read
4. Message: "Please proceed to [Department]"
5. Patient goes to service counter

### Scenario 2: New Patient Joins Queue
1. Patient joins queue in Cardiology
2. **Notification to Cardiology Staff**: "New patient ABC123 joined"
3. Staff can see position and wait time
4. Staff calls when ready

### Scenario 3: Admin Monitoring
1. Admin sees all activities via notifications
2. **Gets notified when**:
   - New patient joins ANY queue
   - Staff calls a patient  
   - Service starts
   - Service completes
   - If any issues occur

## 🔧 Troubleshooting

| Issue | Solution |
|-------|----------|
| No notification bell | Refresh page (Ctrl+F5) |
| Bell shows but no dropdown | Check browser console for JS errors |
| Notifications not appearing | Verify database table exists |
| API returns error | Check server logs for database errors |
| Badge not updating | Try refreshing page or waiting 30 seconds |

## 📊 Expected Notification Flow

```
Patient Action         →  Patient Notified  →  Staff Notified  →  Admin Notified
─────────────────────────────────────────────────────────────────────────────
Join Queue            →  ✅ Queue Joined    →  ✅ New Entry    →  ✅ New Entry
Called Patient        →  ✅ Number Called   →  —              →  ✅ Patient Called
Start Service         →  ✅ Service Start   →  —              →  ✅ Service Start
Complete Service      →  ✅ Service Done    →  —              →  ✅ Service Done
```

## 📱 Notification Features

### Dropdown Menu Shows:
- 5 most recent unread notifications
- Notification title and message
- Time notification was created
- Individual delete button for each
- "Mark all read" button at top

### Badge Shows:
- Red badge with total unread count
- Hides when no unread notifications
- Updates every 30 seconds automatically

### Auto-Refresh:
- Notifications load automatically every 30 seconds
- No need to manually refresh page
- Click notification to mark as read immediately

## 🚀 Next Steps (Optional)

Once notification system is verified working:

1. **Enable Email Notifications** - Configure SMTP settings
2. **Enable SMS Notifications** - Configure SMS gateway
3. **Add Sound Alerts** - Alert staff/patients with audio
4. **Browser Push** - Desktop notifications even if tab not active
5. **Notification Preferences** - Let users choose what to be notified about

## 📞 Support

If notifications aren't working:

1. Check browser console (F12) for JavaScript errors
2. Verify database connection is working
3. Ensure notifications table exists
4. Check that all API files are uploaded
5. Verify user is logged in (sessions working)
6. Clear browser cache (Ctrl+Shift+Delete)
7. Restart browser and try again

## ✅ Verification Checklist

- [ ] Notification bell icon visible on all 3 dashboards
- [ ] Dropdown appears when hovering over bell
- [ ] Badge shows unread count  
- [ ] Test patient created notification successfully
- [ ] Can mark notification as read
- [ ] Can delete notification
- [ ] Can mark all as read
- [ ] Notifications refresh every 30 seconds
- [ ] Test notifications.php passes all checks
- [ ] Staff sees new queue entries
- [ ] Patient sees "called" notification
- [ ] Admin sees all activities