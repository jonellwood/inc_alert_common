#!/bin/bash
# Set correct permissions for RapidSOS webhook system
# Run this on the server from the project root directory

echo "Setting permissions for RapidSOS system..."

# Make all PHP files readable and executable by Apache
echo "Setting PHP files to 644 (readable by web server)..."
find . -type f -name "*.php" -exec chmod 644 {} \;

# Make shell scripts executable
echo "Setting shell scripts to 755 (executable)..."
find . -type f -name "*.sh" -exec chmod 755 {} \;

# Create logs directory if it doesn't exist
if [ ! -d "logs" ]; then
    echo "Creating logs directory..."
    mkdir -p logs
fi

# Set logs directory to be writable by Apache
echo "Setting logs directory to 755..."
chmod 755 logs

# Make existing log files writable
echo "Setting existing log files to 666 (writable by web server)..."
find logs -type f -name "*.log" -exec chmod 666 {} \; 2>/dev/null
find logs -type f -name "*.json" -exec chmod 666 {} \; 2>/dev/null
find logs -type f -name "*.pid" -exec chmod 666 {} \; 2>/dev/null

# Create cache directory if it doesn't exist
if [ ! -d "cache" ]; then
    echo "Creating cache directory..."
    mkdir -p cache
fi

# Set cache directory to be writable by Apache
echo "Setting cache directory to 755..."
chmod 755 cache

# Make cache files writable
echo "Setting cache files to 666..."
find cache -type f -exec chmod 666 {} \; 2>/dev/null

# Create webhooks directory if needed
if [ ! -d "webhooks" ]; then
    echo "Creating webhooks directory..."
    mkdir -p webhooks
fi

# Set all directories to 755 (readable/executable but not writable by web server)
echo "Setting directories to 755..."
find . -type d -exec chmod 755 {} \;

# Special handling for secrets if it exists
if [ -d "secrets" ]; then
    echo "Setting secrets directory to 750 (more restrictive)..."
    chmod 750 secrets
    find secrets -type f -exec chmod 640 {} \;
fi

echo ""
echo "✓ Permissions set!"
echo ""
echo "Summary:"
echo "  - PHP files: 644 (readable by web server)"
echo "  - Shell scripts: 755 (executable)"
echo "  - Directories: 755 (browsable)"
echo "  - Log files: 666 (writable by web server)"
echo "  - Cache files: 666 (writable by web server)"
echo "  - Secrets: 640 (more restrictive)"
echo ""
echo "If you still have permission issues, you may need to:"
echo "  sudo chown -R www-data:www-data logs/ cache/"
echo "  (Replace 'www-data' with your Apache user if different)"
