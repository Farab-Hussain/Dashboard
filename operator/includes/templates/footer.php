    <!-- Load JavaScript -->
    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/dashboard.js?v=<?= time() ?>"></script>
    
    <!-- Add notification system -->
    <div id="notifications" style="position: fixed; top: 20px; right: 20px; z-index: 1000;"></div>
    
    <script>
    // Add CSRF token to all AJAX requests
    document.addEventListener('DOMContentLoaded', function() {
        const token = '<?= generate_csrf_token() ?>';
        const originalFetch = window.fetch;
        window.fetch = function() {
            let [resource, config] = arguments;
            if (config === undefined) {
                config = {};
            }
            if (config.headers === undefined) {
                config.headers = {};
            }
            config.headers['X-CSRF-Token'] = token;
            return originalFetch(resource, config);
        };
    });
    
    // Add notification system
    function showNotification(message, type = 'info') {
        const notifications = document.getElementById('notifications');
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.style.display = 'inline-block';
        notification.style.height = 'auto';
        notification.style.minHeight = 'fit-content';
        notification.style.maxWidth = '300px';
        notification.style.whiteSpace = 'normal';
        notification.style.wordWrap = 'break-word';
        notification.style.marginBottom = '10px';
        notification.textContent = message;
        notifications.appendChild(notification);
        
        // Remove notification after 5 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
    </script>
</body>
</html> 