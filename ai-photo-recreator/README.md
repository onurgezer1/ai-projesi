# AI Photo Recreator WordPress Plugin

A powerful WordPress plugin that allows users to upload photos and recreate them using AI-powered image processing with custom instructions.

## Features

- **Photo Upload**: Users can upload photos in JPEG, PNG, and WebP formats
- **AI Processing**: Recreate photos based on user instructions using AI technology
- **Admin Interface**: Complete admin panel for managing settings and processing
- **Frontend Shortcode**: Easy integration with `[ai_photo_recreator]` shortcode
- **Security**: Built with WordPress security best practices
- **Multilingual**: Turkish language support with i18n preparation
- **File Management**: Automatic cleanup of old files
- **Rate Limiting**: Prevent abuse with configurable rate limits

## Installation

1. Upload the `ai-photo-recreator` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings in the admin panel
4. Use the shortcode `[ai_photo_recreator]` on any page or post

## Usage

### Admin Panel

Access the plugin through the WordPress admin menu:
- **Main Dashboard**: Process photos and view statistics
- **Settings**: Configure upload limits, API keys, and other options
- **History**: View all processing history with filtering options

### Frontend

Use the shortcode to display the photo processing interface:

```
[ai_photo_recreator]
```

Shortcode parameters:
- `title`: Custom title for the form
- `show_title`: Show/hide title (true/false)
- `max_width`: Maximum width of the form container

Example:
```
[ai_photo_recreator title="Transform Your Photos" max_width="600px"]
```

### History Shortcode

Display user processing history:
```
[ai_photo_history]
```

## Configuration

### Settings

- **File Upload**: Configure maximum file size and allowed formats
- **Rate Limiting**: Set requests per hour limit
- **AI Service**: Configure API keys for external AI services
- **File Management**: Set automatic cleanup intervals

### Security Features

- WordPress nonce verification
- User capability checks
- File type validation
- File size restrictions
- Rate limiting protection

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- GD extension for image processing
- Writable upload directory

## File Structure

```
ai-photo-recreator/
├── ai-photo-recreator.php          # Main plugin file
├── admin/                          # Admin interface
│   ├── admin-page.php             # Main admin page
│   ├── settings-page.php          # Settings page
│   ├── history-page.php           # History page
│   ├── css/admin-style.css        # Admin styles
│   └── js/admin-script.js         # Admin JavaScript
├── public/                         # Frontend files
│   ├── css/public-style.css       # Frontend styles
│   └── js/public-script.js        # Frontend JavaScript
├── includes/                       # Core classes
│   ├── class-ai-processor.php     # AI processing logic
│   ├── class-file-handler.php     # File management
│   └── class-shortcode.php        # Shortcode functionality
├── assets/images/                  # Plugin assets
├── languages/                      # Language files
│   └── tr_TR.po                   # Turkish translation
└── README.md                      # Documentation
```

## AI Integration

The plugin is designed to work with various AI services:

- OpenAI DALL-E
- Stable Diffusion
- Custom AI APIs

Currently includes a mock processor for demonstration. To integrate with real AI services, configure the API key in settings and modify the `AI_Photo_Processor` class.

## Development

### Extending the Plugin

1. **Custom AI Processors**: Extend the `AI_Photo_Processor` class
2. **Additional File Formats**: Modify the `AI_Photo_File_Handler` class
3. **New Shortcodes**: Create additional shortcode classes
4. **Custom Hooks**: Use WordPress hooks for extended functionality

### Database Schema

The plugin creates a `wp_ai_photo_history` table:

```sql
CREATE TABLE wp_ai_photo_history (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    original_file varchar(255) NOT NULL,
    processed_file varchar(255) NOT NULL,
    instructions text NOT NULL,
    status varchar(50) NOT NULL DEFAULT 'processing',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    completed_at datetime NULL,
    PRIMARY KEY (id)
);
```

## Support

For support and feature requests, please create an issue on the project repository.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- Photo upload and AI processing
- Admin interface with statistics
- Frontend shortcode support
- Turkish language support
- File management and cleanup
- Rate limiting and security features