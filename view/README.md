# Emergency Alerts Dashboard

A modern Vue3-based web interface for viewing and managing emergency alert data from the RapidSOS integration system.

## Features

### 🔍 **Advanced Filtering & Search**

- **Full-text search** across all fields (contact name, address, description, CFS number, etc.)
- **Status filtering** (Posted, Failed, Pending)
- **Emergency type filtering** with dynamic options based on your data
- **Date range filtering** (Today, This Week, This Month, All Time)
- **Clear filters** button to reset all filters at once

### 📊 **Smart Data Display**

- **Sortable columns** - click any column header to sort
- **Status badges** with color coding (green for posted, red for failed, yellow for pending)
- **Formatted phone numbers** with standard (XXX) XXX-XXXX display
- **Clean address formatting** combining street, city, and state
- **Timestamp formatting** in readable format

### 📱 **Responsive Design**

- **Mobile-friendly** interface that works on all devices
- **Tailwind CSS** for modern, clean styling
- **Font Awesome icons** for visual clarity
- **Loading states** and error handling

### 🗺️ **Interactive Maps**

- **GPS coordinates** displayed for each alert
- **One-click map viewing** opens OpenStreetMap with exact location
- **Map links** in detailed view for precise location reference

### 📄 **Pagination & Performance**

- **Smart pagination** with 25 records per page
- **Page navigation** with visible page numbers
- **Total record counts** and result summaries
- **Optimized queries** for fast loading

### 🔄 **Real-time Updates**

- **Auto-refresh** every 30 seconds
- **Manual refresh** button for immediate updates
- **Keyboard shortcuts** (Escape to close modals, Ctrl+R to refresh)

### 📋 **Detailed Views**

- **Modal pop-ups** with complete alert information
- **Contact information** display
- **Location details** with coordinates
- **Timestamps** for creation and CAD posting
- **Emergency type** and service provider information

## Quick Start

1. **Place files** in your web server's `/view/` directory
2. **Ensure database connection** - Uses same `secrets/db.php` configuration
3. **Open browser** to `/view/index.html`
4. **Start viewing alerts** immediately!

## File Structure

```
/view/
├── index.html          # Main dashboard interface
├── app.js             # Vue3 application logic
└── README.md          # This documentation

/api/
└── getAlerts.php      # Backend API for fetching alert data
```

## API Endpoints

### GET `/api/getAlerts.php`

**Query Parameters:**

- `limit` - Number of records to return (default: 1000)
- `offset` - Record offset for pagination (default: 0)
- `status` - Filter by CAD status (POSTED, FAILED, PENDING)
- `emergency_type` - Filter by emergency type
- `search` - Full-text search across multiple fields

**Response Format:**

```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "total": 150,
        "limit": 25,
        "offset": 0,
        "has_more": true
    }
}
```

## Keyboard Shortcuts

- **Escape** - Close modal windows
- **Ctrl+R** / **Cmd+R** - Refresh data (prevented from page reload)

## Browser Support

- ✅ Chrome 60+
- ✅ Firefox 60+  
- ✅ Safari 12+
- ✅ Edge 79+

## Dependencies (CDN)

- **Vue 3** - Progressive JavaScript framework
- **Tailwind CSS** - Utility-first CSS framework  
- **Font Awesome** - Icon library

*No build process required - runs directly in browser!*

## Customization

### Adding New Columns

Edit the `columns` array in `app.js`:

```javascript
columns: [
    { key: 'fieldName', label: 'Display Name' },
    // ...existing columns
]
```

### Modifying Filters

Add new filter options in the template and corresponding computed properties in the Vue app.

### Styling Changes

Use Tailwind CSS classes or add custom CSS in the `<style>` section of `index.html`.

## Performance Notes

- **Automatic refresh** pauses when modal is open to prevent disruption
- **Client-side filtering** for responsive user experience
- **Optimized database queries** with proper indexing
- **Lazy loading** of large result sets through pagination

## Security

- **CORS enabled** for cross-origin requests
- **SQL injection protection** through prepared statements
- **Input sanitization** on all user inputs
- **Error handling** prevents information disclosure

---

*Built with ❤️ for emergency services professionals*
