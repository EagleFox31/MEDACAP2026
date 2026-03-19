<!-- Carrousel des marques du technicien (Style Blog / Premium) -->
<?php
// Fallback local mapping matching the director dashboard
$localBrandLogos = [
    'RENAULT TRUCK'   => 'renaultTrucks.png', 'HINO' => 'hino_logo.png', 'TOYOTA BT' => 'bt.png',
    'SINOTRUK'        => 'sinotruk.png', 'JCB' => 'jcb.png', 'MERCEDES TRUCK' => 'mercedestruck.png',
    'TOYOTA FORKLIFT' => 'forklift.png', 'FUSO' => 'fuso.png', 'LOVOL' => 'lovol.png',
    'KING LONG'       => 'kl2.png', 'MERCEDES' => 'mercedestruck.png', 'TOYOTA' => 'toyota-logo.png',
    'SUZUKI'          => 'suzuki-logo.png', 'PEUGEOT' => 'peugeot-logo.png', 'MITSUBISHI' => 'mitsubishi-logo.png',
];
?>
<div class="mb-4 position-relative">
    <div class="brand-carousel-wrapper">
        <!-- Bouton flèche gauche -->
        <button class="brand-arrow-btn left mx-2" id="scrollLeft">
            <i class="ki-duotone ki-left fs-1"><span class="path1"></span><span class="path2"></span></i>
        </button>

        <!-- Conteneur scrollable -->
        <div id="brandContainer" class="horizontal-scroll-container">
            <?php foreach ($brandsToShow as $brand) : 
                // Determine logo source
                $logoFile = $localBrandLogos[$brand] ?? ($brandLogos[$brand] ?? 'default.png');
                $logoSrc = "/MEDACAP/public/images/" . $logoFile;
            ?>
            <div class="brand-card-wrapper">
                <div class="card glass-effect depth-effect border-0 brand-card h-100">
                    <div class="card-body d-flex justify-content-center align-items-center p-4">
                        <div class="brand-logo-container d-flex justify-content-center align-items-center w-100 h-100">
                            <img src="<?php echo htmlspecialchars($logoSrc); ?>"
                                 alt="<?php echo htmlspecialchars($brand); ?> Logo"
                                 class="img-fluid brand-logo-img"
                                 title="<?php echo htmlspecialchars($brand); ?>"
                                 onerror="this.src='/MEDACAP/public/images/default.png'">
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Bouton flèche droite -->
        <button class="brand-arrow-btn right mx-2" id="scrollRight">
            <i class="ki-duotone ki-right fs-1"><span class="path1"></span><span class="path2"></span></i>
        </button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('brandContainer');
            const scrollAmount = 320; // Scroll a bit more than one card width

            document.getElementById('scrollLeft').addEventListener('click', function() {
                container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            });

            document.getElementById('scrollRight').addEventListener('click', function() {
                container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            });
        });
    </script>
</div>

<style>
    .brand-carousel-wrapper {
        position: relative;
        padding: 0 1rem; /* Reduced from 4rem to close the side gaps */
        display: flex;
        align-items: center;
    }

    .horizontal-scroll-container {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        scroll-behavior: smooth;
        gap: 1.5rem;
        padding: 1rem 0.5rem; /* Add padding for box-shadow visibility */
        -ms-overflow-style: none;
        scrollbar-width: none;
        width: 100%;
        justify-content: center;
    }

    .horizontal-scroll-container::-webkit-scrollbar {
        display: none;
    }

    .brand-card-wrapper {
        flex: 0 0 auto;
        width: 220px; /* Wider, more blog-like card */
        height: 160px;
    }

    .brand-card {
        border-radius: 16px;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        cursor: pointer;
        background: linear-gradient(145deg, rgba(255,255,255,0.9), rgba(255,255,255,0.6)) !important;
    }

    .brand-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
    }

    .brand-logo-container {
        height: 100%;
        width: 100%;
    }

    .brand-logo-img {
        max-height: 90%;
        max-width: 90%;
        object-fit: contain;
        filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.05));
    }

    .brand-arrow-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.8) !important;
        border: 1px solid rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(5px);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
        color: var(--primary-navy, #1e3a8a);
    }

    .brand-arrow-btn:hover {
        background: white !important;
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    .brand-arrow-btn.left { left: 0; }
    .brand-arrow-btn.right { right: 0; }
</style>