<?php
// includes/footer.php
?>

    </div> 
</div> 
<div id="logoutModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-red-100 rounded-full p-2">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-800">Konfirmasi Keluar</h3>
                <p class="mt-1 text-gray-600">Apakah Anda yakin ingin keluar dari aplikasi?</p>
            </div>
        </div>
        <div class="mt-6 flex justify-end space-x-3">
            <button
                type="button"
                onclick="closeLogoutModal()"
                class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                Batal
            </button>
            <a
                href="<?php echo BASE_URL; ?>/logout.php"
                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200 no-underline">
                Ya, Keluar
            </a>
        </div>
    </div>
</div>

<script>
    // Script untuk Modal Logout (Sudah ada)
    const logoutModal = document.getElementById('logoutModal');
    
    function openLogoutModal() {
        if (logoutModal) logoutModal.classList.remove('hidden');
    }

    function closeLogoutModal() {
        if (logoutModal) logoutModal.classList.add('hidden');
    }
    
    // Menutup modal jika klik di luar area modal (backdrop)
    window.addEventListener('click', function(event) {
        if (event.target == logoutModal) {
            closeLogoutModal();
        }
    });
</script>

<style>
    /* Menambahkan style untuk transisi rotasi ikon */
    .sidebar-link svg {
        transition: transform 0.2s ease-in-out;
    }
    .sidebar-link svg.rotate-90 {
        transform: rotate(90deg);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Ambil semua tombol toggle
        const menuToggles = document.querySelectorAll('[data-menu-toggle]');

        menuToggles.forEach(button => {
            button.addEventListener('click', function() {
                // 2. Ambil ID submenu dari atribut data
                const targetId = this.getAttribute('data-menu-toggle');
                const targetMenu = document.getElementById(targetId);

                if (targetMenu) {
                    // 3. Tampilkan/sembunyikan submenu
                    targetMenu.classList.toggle('hidden');

                    // 4. Ambil ikon SVG di dalam tombol
                    const icon = this.querySelector('svg');
                    if (icon) {
                        // 5. Putar ikon
                        icon.classList.toggle('rotate-90');
                    }
                }
            });
        });
    });
</script>

<script>
// Script untuk Mobile Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    // Fungsi untuk membuka sidebar
    function openSidebar() {
        if (sidebar && sidebarBackdrop) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            sidebarBackdrop.classList.remove('hidden');
        }
    }

    // Fungsi untuk menutup sidebar
    function closeSidebar() {
        if (sidebar && sidebarBackdrop) {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            sidebarBackdrop.classList.add('hidden');
        }
    }

    // Event listener untuk tombol hamburger
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation(); // Hentikan event agar tidak ditangkap backdrop
            // Cek apakah sidebar sedang terbuka atau tertutup
            if (sidebar.classList.contains('-translate-x-full')) {
                openSidebar();
            } else {
                closeSidebar();
            }
        });
    }

    // Event listener untuk backdrop (klik di area gelap)
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function() {
            closeSidebar();
        });
    }
});
</script>
</body>
</html>