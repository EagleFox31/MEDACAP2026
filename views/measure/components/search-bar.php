<?php
function renderSearchBar(string $placeholder = 'Rechercher...', string $inputId = 'search', int $widthPx = 300): void {
    ?>
    <!-- Glass Effect Modern Search Bar -->
    <div class="modern-search-container" style="width: <?= intval($widthPx) ?>px;">
        <div class="search-input-wrapper">
            <i class="ki-duotone ki-magnifier search-icon">
                <span class="path1"></span><span class="path2"></span>
            </i>
            <input
                    type="text"
                    id="<?= htmlspecialchars($inputId) ?>"
                    class="search-input"
                    placeholder="<?= htmlspecialchars($placeholder) ?>"
                    autocomplete="off"
            >
            <div class="search-actions">
                <button type="button" class="btn-clear d-none">
                    <i class="ki-duotone ki-cross fs-2"></i>
                </button>
            </div>
        </div>
        <div class="search-border-animation"></div>
    </div>

    <style>
        .modern-search-container {
            position: relative;
            margin: 0.5rem 0;
        }

        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 16px;
            padding: 0.85rem 1.75rem;
            transition: all 0.3s ease;
            box-shadow:
                    0 8px 32px rgba(0, 0, 0, 0.25),
                    inset 0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .search-input-wrapper:focus-within {
            background: rgba(255, 255, 255, 0.15);
            box-shadow:
                    0 12px 40px rgba(0, 0, 0, 0.3),
                    inset 0 0 0 1px rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
        }

        .search-icon {
            font-size: 1.4rem;
            color: rgba(255, 255, 255, 0.9);
            margin-right: 0.85rem;
            transition: all 0.3s ease;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
        }

        .search-input-wrapper:focus-within .search-icon {
            color: #8B5CF6;
            transform: scale(1.05);
        }

        .search-input {
            flex: 1;
            background: transparent;
            border: none;
            color: #FFFFFF;
            font-size: 1.1rem;
            font-weight: 500;
            padding: 0.6rem 0;
            outline: none;
            letter-spacing: 0.02em;
            text-shadow: 0 1px 3px rgba(0,0,0,0.25);
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.65);
            opacity: 0.9;
            font-weight: 400;
            letter-spacing: 0.01em;
        }

        .search-actions {
            display: flex;
            align-items: center;
            margin-left: 0.6rem;
        }

        .btn-clear {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            padding: 0.4rem;
            border-radius: 50%;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .btn-clear:hover {
            color: #FFFFFF;
            background: rgba(239, 68, 68, 0.4);
            transform: scale(1.15);
            box-shadow: 0 3px 12px rgba(239, 68, 68, 0.25);
        }

        .search-border-animation {
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, #7C3AED, #8B5CF6, #6366F1);
            border-radius: 3px;
            transform: translateX(-50%);
            transition: width 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 2px 12px rgba(123, 92, 245, 0.45);
            z-index: 2;
        }

        .search-input:focus ~ .search-border-animation {
            width: 100%;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('<?= $inputId ?>');
            const wrapper = searchInput.closest('.search-input-wrapper');
            const clearBtn = wrapper.querySelector('.btn-clear');

            // Update clear button visibility
            const updateClearButton = () => {
                if (searchInput.value.length > 0) {
                    clearBtn.classList.remove('d-none');
                } else {
                    clearBtn.classList.add('d-none');
                }
            };

            searchInput.addEventListener('input', updateClearButton);

            // Clear functionality
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.focus();
                updateClearButton();

                // Trigger custom event for search clearing
                const event = new Event('searchCleared');
                searchInput.dispatchEvent(event);
            });

            // Initialize
            updateClearButton();

            // Add focus class to wrapper when input is focused
            searchInput.addEventListener('focus', () => {
                wrapper.classList.add('focused');
            });

            searchInput.addEventListener('blur', () => {
                wrapper.classList.remove('focused');
            });
        });
    </script>
    <?php
}
?>