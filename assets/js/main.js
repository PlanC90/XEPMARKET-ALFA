/**
 * XepMarket Main JS
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // 1. GLOBAL SEARCH OVERLAY LOGIC
        var $searchOverlay = $('#global-search-overlay');
        var $searchInput = $('.search-field-modern');
        var $searchResults = $('#live-search-results');
        var searchTimeout;

        /* Search Overlay Logic (Moved to footer.php)
        $(document).on('click', '#header-search-open', function (e) {
            e.preventDefault();
            if ($searchOverlay.length) {
                $searchOverlay.css('display', 'flex').hide().fadeIn(200).addClass('active');
                $('body').addClass('search-open');
                setTimeout(function () {
                    if ($searchInput.length) $searchInput.focus();
                }, 300);
            }
        });

        $(document).on('click', '#header-search-close-btn, .search-overlay-close-area', function () {
            if ($searchOverlay.length) {
                $searchOverlay.fadeOut(200, function () {
                    $(this).removeClass('active').css('display', 'none');
                });
            }
            $('body').removeClass('search-open');
            if ($searchResults.length) {
                $searchResults.empty().removeClass('active');
            }
            if ($searchInput.length) $searchInput.val('');
        });

        $(document).keyup(function (e) {
            if (e.key === "Escape" && $searchOverlay.hasClass('active')) {
                $searchOverlay.fadeOut(200, function () {
                    $(this).removeClass('active').css('display', 'none');
                });
                $('body').removeClass('search-open');
                if ($searchResults.length) {
                    $searchResults.empty().removeClass('active');
                }
                if ($searchInput.length) $searchInput.val('');
            }
        });
        */

        /* Live Search AJAX (Moved to footer.php for high reliability)
        if ($searchInput.length) {
            $searchInput.on('input', function () {
                var query = $(this).val();
                if (searchTimeout) clearTimeout(searchTimeout);
                if (query.length < 2) {
                    if ($searchResults.length) $searchResults.empty().removeClass('active');
                    return;
                }
                searchTimeout = setTimeout(function () {
                    if ($searchResults.length) $searchResults.addClass('loading');
                    $.ajax({
                        url: (typeof xep_live_search !== 'undefined') ? xep_live_search.ajax_url : '/wp-admin/admin-ajax.php',
                        type: 'POST',
                        data: {
                            action: 'xep_live_search',
                            query: query,
                            nonce: (typeof xep_live_search !== 'undefined') ? xep_live_search.nonce : ''
                        },
                        success: function (response) {
                            if ($searchResults.length) {
                                $searchResults.removeClass('loading');
                                if (response.success && response.data.html) {
                                    $searchResults.html(response.data.html).addClass('active');
                                } else {
                                    $searchResults.empty().removeClass('active');
                                }
                            }
                        },
                        error: function () {
                            if ($searchResults.length) $searchResults.removeClass('loading');
                        }
                    });
                }, 400);
            });
        }
        */

        // 2. SCROLL EFFECTS
        $(window).scroll(function () {
            if ($(window).scrollTop() > 50) {
                $('.site-header').addClass('scrolled');
            } else {
                $('.site-header').removeClass('scrolled');
            }

            var $scrollTopBtn = $('#scroll-to-top');
            if ($scrollTopBtn.length) {
                if ($(window).scrollTop() > 300) {
                    $scrollTopBtn.addClass('show');
                } else {
                    $scrollTopBtn.removeClass('show');
                }
            }
        });

        $(document).on('click', '#scroll-to-top', function (e) {
            e.preventDefault();
            $('html, body').animate({ scrollTop: 0 }, 800);
        });

        // 3. ANCHOR LINKS
        $('a[href*="#"]').not('[href="#"]').not('[href="#0"]').click(function (event) {
            if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
                var target = $(this.hash);
                target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
                if (target.length) {
                    event.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 100
                    }, 1000);
                }
            }
        });

        // 4. BANNER HANDLING
        // 4. TOP BANNER CLOSE LOGIC
        var $banner = $('.top-announcement-banner');

        // Function to set cookie
        function setBannerCookie(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/";
        }

        if ($banner.length && localStorage.getItem('banner_closed') === 'true') {
            $banner.hide();
            // Ensure cookie is also set if localStorage is set (sync)
            setBannerCookie('omnixep_banner_closed', 'true', 30);
        }

        $(document).on('click', '.banner-close', function () {
            var bannerHeight = $banner.outerHeight();
            var currentPadding = parseInt($('body').css('padding-top'));
            $banner.slideUp(300, function () {
                if (currentPadding > 0) {
                    $('body').animate({
                        'padding-top': (currentPadding - bannerHeight) + 'px'
                    }, 300);
                }
                localStorage.setItem('banner_closed', 'true');
                setBannerCookie('omnixep_banner_closed', 'true', 30);
            });
        });

        // 5. MOBILE MENU
        $(document).on('click', '#mobile-menu-toggle, #mobile-menu-close, #mobile-menu-overlay', function () {
            $('.mobile-navigation').toggleClass('active');
            $('.mobile-menu-overlay').toggleClass('active');
            $('body').toggleClass('menu-open');
        });

        $(document).on('click', '.mobile-navigation a', function () {
            $('.mobile-navigation').removeClass('active');
            $('.mobile-menu-overlay').removeClass('active');
            $('body').removeClass('menu-open');
        });

        // 6. QUANTITY BUTTONS
        function setupQuantityButtons() {
            $('.quantity').each(function () {
                var $q = $(this);
                if ($q.find('.qty-btn').length > 0) return;
                var $input = $q.find('.qty');
                var $minus = $('<div class="qty-btn minus">-</div>');
                var $plus = $('<div class="qty-btn plus">+</div>');
                $input.before($minus);
                $input.after($plus);
                $minus.on('click', function () {
                    var val = parseInt($input.val());
                    var min = parseInt($input.attr('min')) || 1;
                    if (val > min) $input.val(val - 1).trigger('change');
                });
                $plus.on('click', function () {
                    var val = parseInt($input.val());
                    var max = parseInt($input.attr('max')) || 999;
                    if (val < max) $input.val(val + 1).trigger('change');
                });
            });
        }
        setupQuantityButtons();
        $(document.body).on('updated_cart_totals', setupQuantityButtons);

        // 7. AUTO UPDATE CART
        var cartTimer;
        $(document.body).on('change input', '.woocommerce-cart-form input.qty', function () {
            var $form = $('.woocommerce-cart-form');
            if ($form.length === 0) return;
            if (cartTimer) clearTimeout(cartTimer);
            cartTimer = setTimeout(function () {
                $form.find('button[name="update_cart"]').prop('disabled', false).trigger('click');
            }, 800);
        });

        // 8. CUSTOM VARIATIONS
        var colorMap = {
            'Siyah': '#000000', 'Black': '#000000', 'Beyaz': '#ffffff', 'White': '#ffffff',
            'Kırmızı': '#ff3b30', 'Red': '#ff3b30', 'Mavi': '#007aff', 'Blue': '#007aff',
            'Yeşil': '#34c759', 'Green': '#34c759', 'Sarı': '#ffcc00', 'Yellow': '#ffcc00',
            'Mor': '#af52de', 'Purple': '#af52de', 'Pembe': '#ff2d55', 'Pink': '#ff2d55',
            'Turuncu': '#ff9500', 'Orange': '#ff9500', 'Gri': '#8e8e93', 'Gray': '#8e8e93'
        };

        function setupCustomDropdowns() {
            $('.variations select').each(function () {
                var $select = $(this);
                var $td = $select.closest('td');
                if ($td.find('.alpha-select-wrap').length > 0) return;
                var $wrapper = $('<div class="alpha-select-wrap"></div>');
                var $current = $('<div class="alpha-select-current"></div>');
                var $optionsList = $('<div class="alpha-select-options"></div>');

                var updateCurrentDisplay = function () {
                    var $activeOpt = $select.find('option:selected');
                    var text = $activeOpt.text() || 'Choose an option';
                    $current.empty();
                    var colorCode = colorMap[text] || colorMap[text.split(' ')[text.split(' ').length - 1]];
                    if (colorCode) {
                        $current.append($('<span class="alpha-current-dot"></span>').css('background', colorCode));
                    }
                    $current.append($('<span></span>').text(text));
                };

                updateCurrentDisplay();

                $select.find('option').each(function () {
                    var $opt = $(this);
                    var val = $opt.val();
                    var text = $opt.text();
                    if (!val) return;
                    var $customOpt = $('<div class="alpha-select-option"></div>').attr('data-value', val);
                    var colorCode = colorMap[text] || colorMap[text.split(' ')[text.split(' ').length - 1]];
                    if (colorCode) {
                        $customOpt.append($('<span class="alpha-opt-dot"></span>').css('background', colorCode));
                    }
                    $customOpt.append($('<span class="alpha-opt-text"></span>').text(text));
                    if ($opt.is(':selected')) $customOpt.addClass('active');
                    $customOpt.on('click', function (e) {
                        e.stopPropagation();
                        $select.val(val).trigger('change');
                        updateCurrentDisplay();
                        $optionsList.find('.alpha-select-option').removeClass('active');
                        $(this).addClass('active');
                        $wrapper.removeClass('open');
                    });
                    $optionsList.append($customOpt);
                });

                $current.on('click', function (e) {
                    e.stopPropagation();
                    $('.alpha-select-wrap').not($wrapper).removeClass('open');
                    $wrapper.toggleClass('open');
                });

                $wrapper.append($current).append($optionsList);
                $select.hide().after($wrapper);
            });
        }
        setupCustomDropdowns();
        $(document.body).on('woocommerce_variation_has_changed', setupCustomDropdowns);
        $(document).on('click', function () { $('.alpha-select-wrap').removeClass('open'); });

        // 9. HERO SLIDER LOGIC
        var $slides = $('.slide-item');
        var $nextBtn = $('.next-slide');
        var $prevBtn = $('.prev-slide');
        var currentSlide = 0;
        var slideInterval;

        function showSlide(index) {
            $slides.removeClass('active');
            $slides.eq(index).addClass('active');
            currentSlide = index;
        }

        function nextSlide() {
            var next = (currentSlide + 1) % $slides.length;
            showSlide(next);
        }

        function startAutoSlide() {
            if (slideInterval) clearInterval(slideInterval);
            slideInterval = setInterval(nextSlide, 5000);
        }

        if ($slides.length > 1) {
            startAutoSlide();

            $nextBtn.on('click', function (e) {
                e.preventDefault();
                nextSlide();
                startAutoSlide();
            });

            $prevBtn.on('click', function (e) {
                e.preventDefault();
                var prev = (currentSlide - 1 + $slides.length) % $slides.length;
                showSlide(prev);
                startAutoSlide();
            });

            // Dot navigation (if dots exist)
            var $dotsContainer = $('.slider-dots');
            if ($dotsContainer.length) {
                $slides.each(function (i) {
                    var $dot = $('<div class="slider-dot"></div>');
                    if (i === 0) $dot.addClass('active');
                    $dot.on('click', function () {
                        showSlide(i);
                        startAutoSlide();
                    });
                    $dotsContainer.append($dot);
                });

                // Wrap showSlide to update dots
                var baseShowSlide = showSlide;
                showSlide = function (index) {
                    baseShowSlide(index);
                    $('.slider-dot').removeClass('active').eq(index).addClass('active');
                };
            }

            // Touch Swipe Support for mobile browsers
            var touchStartX = 0;
            var touchEndX = 0;
            var sliderElement = document.querySelector('.hero-slider-wrapper');

            if (sliderElement) {
                sliderElement.addEventListener('touchstart', function (e) {
                    touchStartX = e.changedTouches[0].screenX;
                }, { passive: true });

                sliderElement.addEventListener('touchend', function (e) {
                    touchEndX = e.changedTouches[0].screenX;
                    handleSwipe();
                });
            }

            function handleSwipe() {
                var distance = touchEndX - touchStartX;
                if (Math.abs(distance) > 40) { // minimum threshold
                    if (distance < 0) {
                        // swipe left
                        nextSlide();
                    } else {
                        // swipe right
                        var prev = (currentSlide - 1 + $slides.length) % $slides.length;
                        showSlide(prev);
                    }
                    startAutoSlide();
                }
            }
        }

        // 10. SALE CAROUSEL LOGIC — sonsuz döngü; mobilde tek ürün
        var $saleCarousel = $('.sale-carousel-wrapper ul.products');
        if ($saleCarousel.length) {
            var el = $saleCarousel[0];
            var getItemWidth = function () {
                var w = $saleCarousel.find('li.product').first().outerWidth(true);
                return (w && w > 0) ? w : 300;
            };
            var hasScroll = function () { return el.scrollWidth > el.clientWidth + 2; };
            var isAtStart = function () { return el.scrollLeft <= 2; };
            var isAtEnd = function () { return el.scrollLeft >= el.scrollWidth - el.clientWidth - 2; };

            var programmaticScroll = false;
            $('.sale-next').on('click', function (e) {
                e.preventDefault();
                if (!hasScroll()) return;
                programmaticScroll = true;
                var itemWidth = getItemWidth();
                if (isAtEnd()) {
                    el.scrollLeft = 0;
                } else {
                    el.scrollBy({ left: itemWidth, behavior: 'smooth' });
                }
            });
            $('.sale-prev').on('click', function (e) {
                e.preventDefault();
                if (!hasScroll()) return;
                programmaticScroll = true;
                var itemWidth = getItemWidth();
                if (isAtStart()) {
                    el.scrollLeft = el.scrollWidth - el.clientWidth;
                } else {
                    el.scrollBy({ left: -itemWidth, behavior: 'smooth' });
                }
            });

            // Scroll sonunda sonsuz döngü: sadece kullanıcı elle kaydırdığında sarma; butonla kaydırmada üründe kal
            var scrollEndTimer;
            var justWrapped = false;
            el.addEventListener('scroll', function () {
                clearTimeout(scrollEndTimer);
                scrollEndTimer = setTimeout(function () {
                    if (justWrapped) { justWrapped = false; return; }
                    if (programmaticScroll) { programmaticScroll = false; return; }
                    if (!hasScroll()) return;
                    if (el.scrollLeft >= el.scrollWidth - el.clientWidth - 2) {
                        el.scrollLeft = 0;
                        justWrapped = true;
                    } else if (el.scrollLeft <= 2) {
                        el.scrollLeft = el.scrollWidth - el.clientWidth;
                        justWrapped = true;
                    }
                }, 250);
            }, { passive: true });
        }

    });
})(jQuery);
