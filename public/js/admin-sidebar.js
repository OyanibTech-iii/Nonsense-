// Admin Sidebar Mobile Menu Toggle Functionality
document.addEventListener('DOMContentLoaded', function () {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const sidebar = document.getElementById('sidebar');
    const mobileOverlay = document.getElementById('mobile-overlay');
    const mainHeader = document.getElementById('main-header');

    if (!mobileMenuButton || !sidebar || !mobileOverlay || !mainHeader) {
        console.warn('Admin sidebar elements not found');
        return;
    }

    function toggleSidebar() {
        sidebar.classList.toggle('-translate-x-full');
        mobileOverlay.classList.toggle('hidden');

        // Hide/show header on mobile when sidebar is open
        if (window.innerWidth < 1024) {
            mainHeader.classList.toggle('hidden');
        }
    }

    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        mobileOverlay.classList.add('hidden');

        // Show header when sidebar is closed on mobile
        if (window.innerWidth < 1024) {
            mainHeader.classList.remove('hidden');
        }
    }

    // Toggle sidebar on button click
    mobileMenuButton.addEventListener('click', toggleSidebar);

    // Close sidebar when clicking overlay
    mobileOverlay.addEventListener('click', closeSidebar);

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function (event) {
        if (window.innerWidth < 1024 && 
            !sidebar.contains(event.target) && 
            !mobileMenuButton.contains(event.target)) {
            closeSidebar();
        }
    });

    // Handle window resize
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1024) {
            closeSidebar();
            // Ensure header is visible on desktop
            mainHeader.classList.remove('hidden');
        }
    });
});
