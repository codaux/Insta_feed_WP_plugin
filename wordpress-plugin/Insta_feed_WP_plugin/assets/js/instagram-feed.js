(function() {
    window.InstaFeedFrontendLoaded = true;
    window.InstaFeedDebug = window.InstaFeedDebug || {};

    function updateDebug(values) {
        Object.assign(window.InstaFeedDebug, values, {
            loaded: true,
            updatedAt: new Date().toISOString()
        });
    }

    updateDebug({
        attempts: 0,
        buttonsFound: 0,
        feedsFound: 0,
        initialized: 0,
        fetchStarted: 0,
        lastMessage: 'script loaded'
    });

    function getAjaxUrl() {
        if (typeof rezaGrpxl !== 'undefined' && rezaGrpxl.ajax_url) {
            return rezaGrpxl.ajax_url;
        }

        if (typeof Insta_feed_WP_plugin !== 'undefined' && Insta_feed_WP_plugin.ajax_url) {
            return Insta_feed_WP_plugin.ajax_url;
        }

        if (typeof ajaxurl !== 'undefined') {
            return ajaxurl;
        }

        return '/wp-admin/admin-ajax.php';
    }

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }

        window.addEventListener('load', callback, { once: true });
    }

    function initAllInstagramFeeds() {
        document.documentElement.setAttribute('data-instagram-feed-js', 'loaded');

        const ajaxUrl = getAjaxUrl();
        const feedWraps = document.querySelectorAll('.Insta_feed_WP_plugin-feed-wrap');
        const legacyFeeds = document.querySelectorAll('#instagram-feed');
        const loadMoreButtons = document.querySelectorAll('#load-more-instagram, .Insta_feed_WP_plugin-load-more');
        let initialized = 0;

        updateDebug({
            attempts: (window.InstaFeedDebug.attempts || 0) + 1,
            buttonsFound: loadMoreButtons.length,
            feedsFound: legacyFeeds.length + document.querySelectorAll('.Insta_feed_WP_plugin-feed').length,
            ajaxUrl: ajaxUrl,
            lastMessage: 'init attempted'
        });

        feedWraps.forEach(feedWrap => {
            if (initInstagramFeed(feedWrap, ajaxUrl)) {
                initialized++;
            }
        });

        legacyFeeds.forEach(legacyFeed => {
            const root = legacyFeed.parentElement || document;
            if (initInstagramFeed(root, ajaxUrl)) {
                initialized++;
            }
        });

        loadMoreButtons.forEach(button => {
            const root = button.closest('.Insta_feed_WP_plugin-feed-wrap') || button.parentElement || document;
            let feed = root.querySelector('#instagram-feed, .Insta_feed_WP_plugin-feed');

            if (!feed && button.parentNode) {
                feed = document.createElement('div');
                feed.id = 'instagram-feed';
                button.parentNode.insertBefore(feed, button);
                updateDebug({
                    createdMissingFeed: true,
                    lastMessage: 'created missing feed container before load more button'
                });
            }

            if (feed && initInstagramFeed(root, ajaxUrl)) {
                initialized++;
            }
        });

        updateDebug({
            initialized: (window.InstaFeedDebug.initialized || 0) + initialized,
            lastMessage: initialized > 0 ? 'feed initialized' : 'no uninitialized feed found'
        });
    }

    function findInRoot(root, selector) {
        return root.querySelector ? root.querySelector(selector) : null;
    }

    function safeParseSlides(photo) {
        try {
            return JSON.parse(photo.getAttribute('data-slides') || '[]');
        } catch (e) {
            console.error('Invalid Instagram slide data:', e);
            return [];
        }
    }

    function initInstagramFeed(root, ajaxUrl) {
        const instagramFeed = (root.matches && root.matches('.Insta_feed_WP_plugin-feed, #instagram-feed'))
            ? root
            : findInRoot(root, '.Insta_feed_WP_plugin-feed') || findInRoot(root, '#instagram-feed');
        if (!instagramFeed || instagramFeed.dataset.instagramFeedInitialized === '1') {
            return false;
        }

        instagramFeed.dataset.instagramFeedInitialized = '1';

        const loadMoreBtn = findInRoot(root, '.Insta_feed_WP_plugin-load-more') || findInRoot(root, '#load-more-instagram');
        const modal = findInRoot(root, '.Insta_feed_WP_plugin-modal') || findInRoot(root, '#image-modal');
        const modalOverlay = findInRoot(root, '.Insta_feed_WP_plugin-modal-overlay') || findInRoot(root, '#modal-overlay');
        const modalImageOld = findInRoot(root, '.Insta_feed_WP_plugin-modal-image-old') || findInRoot(root, '#modal-image-old');
        const modalImageNew = findInRoot(root, '.Insta_feed_WP_plugin-modal-image-new') || findInRoot(root, '#modal-image-new');
        const modalVideoOld = findInRoot(root, '#modal-video-old');
        const modalVideoNew = findInRoot(root, '#modal-video-new');
        const modalCaptionText = findInRoot(root, '#modal-caption-text');
        const modalPrevBtn = findInRoot(root, '#modal-prev-btn');
        const modalNextBtn = findInRoot(root, '#modal-next-btn');
        const modalCloseBtn = findInRoot(root, '#modal-close-btn');
        const defaultButtonText = loadMoreBtn ? (loadMoreBtn.textContent.trim() || 'Show More') : 'Show More';
        const debugMode = new URLSearchParams(window.location.search).has('instagram_feed_debug');
        const statusEl = document.createElement('div');

        statusEl.className = 'instagram-feed-status';
        statusEl.style.display = 'none';

        if (loadMoreBtn && loadMoreBtn.parentNode) {
            loadMoreBtn.parentNode.insertBefore(statusEl, loadMoreBtn.nextSibling);
        } else {
            instagramFeed.insertAdjacentElement('afterend', statusEl);
        }

        let currentIntervals = new Map();
        let currentSlides = [];
        let currentIndex = 0;
        let currentCaption = '';
        let slideInterval;
        let msnry = null;
        let isFetching = false;

        function slideImages(container, nextSrc) {
            if (!container) return;

            const oldImg = container.querySelector('.slide-img-old');
            const newImg = container.querySelector('.slide-img-new');
            if (!oldImg || !newImg) return;

            newImg.src = nextSrc;

            function doAnimation() {
                oldImg.style.transition = 'none';
                newImg.style.transition = 'none';
                oldImg.style.transform = 'translateX(0)';
                newImg.style.transform = 'translateX(100%)';

                requestAnimationFrame(() => {
                    oldImg.style.transition = 'transform 0.5s ease';
                    newImg.style.transition = 'transform 0.5s ease';
                    oldImg.style.transform = 'translateX(-100%)';
                    newImg.style.transform = 'translateX(0)';
                });

                setTimeout(() => {
                    oldImg.src = newImg.src;
                    oldImg.style.transition = 'none';
                    newImg.style.transition = 'none';
                    oldImg.style.transform = 'translateX(0)';
                    newImg.style.transform = 'translateX(100%)';
                }, 500);
            }

            if (newImg.complete) {
                doAnimation();
            } else {
                newImg.onload = doAnimation;
            }
        }

        function slideImagesModal(nextSrc) {
            if (!modalImageOld || !modalImageNew) return;

            modalImageNew.src = nextSrc;

            function doAnimation() {
                modalImageOld.style.transition = 'none';
                modalImageNew.style.transition = 'none';
                modalImageOld.style.transform = 'translateX(0)';
                modalImageNew.style.transform = 'translateX(100%)';

                requestAnimationFrame(() => {
                    modalImageOld.style.transition = 'transform 0.5s ease';
                    modalImageNew.style.transition = 'transform 0.5s ease';
                    modalImageOld.style.transform = 'translateX(-100%)';
                    modalImageNew.style.transform = 'translateX(0)';
                });

                setTimeout(() => {
                    modalImageOld.src = modalImageNew.src;
                    modalImageOld.style.transition = 'none';
                    modalImageNew.style.transition = 'none';
                    modalImageOld.style.transform = 'translateX(0)';
                    modalImageNew.style.transform = 'translateX(100%)';
                }, 500);
            }

            if (modalImageNew.complete) {
                doAnimation();
            } else {
                modalImageNew.onload = doAnimation;
            }
        }

        function startSlideShow(photo) {
            const slides = safeParseSlides(photo);
            if (slides.length < 2) return;

            let index = 1;
            const container = photo.querySelector('.slide-container');
            const interval = setInterval(() => {
                slideImages(container, slides[index].thumb);
                index = (index + 1) % slides.length;
            }, 2000);

            currentIntervals.set(photo, interval);
        }

        function stopSlideShow(photo) {
            if (currentIntervals.has(photo)) {
                clearInterval(currentIntervals.get(photo));
                currentIntervals.delete(photo);
            }

            const slides = safeParseSlides(photo);
            if (slides.length === 0) return;

            const container = photo.querySelector('.slide-container');
            if (!container) return;

            const oldImg = container.querySelector('.slide-img-old');
            const newImg = container.querySelector('.slide-img-new');
            if (!oldImg || !newImg) return;

            oldImg.src = slides[0].thumb;
            oldImg.style.transform = 'translateX(0)';
            newImg.style.transform = 'translateX(100%)';
        }

        function isVideoSlide(slide) {
            return slide && slide.type === 'VIDEO';
        }

        function resetModalImages() {
            if (!modalImageOld || !modalImageNew) return;

            modalImageOld.style.display = 'block';
            modalImageNew.style.display = 'block';
            modalImageOld.style.transition = 'none';
            modalImageNew.style.transition = 'none';
            modalImageOld.style.transform = 'translateX(0)';
            modalImageNew.style.transform = 'translateX(100%)';
        }

        function clearModalVideos() {
            [modalVideoOld, modalVideoNew].forEach(video => {
                if (!video) return;

                video.pause();
                video.removeAttribute('src');
                video.load();
                video.style.display = 'none';
            });
        }

        function showModalVideo(src) {
            if (!modalVideoOld) return;

            if (modalImageOld) modalImageOld.style.display = 'none';
            if (modalImageNew) modalImageNew.style.display = 'none';
            if (modalVideoNew) modalVideoNew.style.display = 'none';

            modalVideoOld.src = src;
            modalVideoOld.style.display = 'block';
            modalVideoOld.load();
        }

        function updateModalCaption() {
            if (!modalCaptionText) return;

            modalCaptionText.textContent = currentCaption || '';
            modalCaptionText.setAttribute('dir', 'auto');
        }

        function updateModalControls() {
            const hasMultipleSlides = currentSlides.length > 1;

            [modalPrevBtn, modalNextBtn].forEach(button => {
                if (!button) return;

                button.disabled = !hasMultipleSlides;
                button.style.opacity = hasMultipleSlides ? '1' : '0.35';
                button.style.cursor = hasMultipleSlides ? 'pointer' : 'default';
            });
        }

        function showCurrentModalSlide(animate) {
            const slide = currentSlides[currentIndex];
            if (!slide) return;

            updateModalCaption();
            updateModalControls();

            if (isVideoSlide(slide)) {
                clearInterval(slideInterval);
                clearModalVideos();
                showModalVideo(slide.full);
                return;
            }

            clearModalVideos();
            resetModalImages();

            if (animate) {
                slideImagesModal(slide.full);
            } else if (modalImageOld) {
                modalImageOld.src = slide.full;
            }
        }

        function moveModalSlide(direction) {
            if (currentSlides.length < 2) return;

            currentIndex = (currentIndex + direction + currentSlides.length) % currentSlides.length;
            showCurrentModalSlide(true);
        }

        function startModalAutoSlide() {
            clearInterval(slideInterval);

            if (currentSlides.length < 2 || currentSlides.some(isVideoSlide)) {
                return;
            }

            slideInterval = setInterval(() => moveModalSlide(1), 2000);
        }

        function openModal(photo) {
            currentSlides = safeParseSlides(photo);
            if (currentSlides.length === 0 || !modal || !modalOverlay || !modalImageOld) {
                return;
            }

            currentIndex = 0;
            currentCaption = photo.getAttribute('data-caption') || '';
            resetModalImages();
            showCurrentModalSlide(false);
            modal.style.display = 'flex';
            startModalAutoSlide();
            modalOverlay.addEventListener('click', closeModal, { once: true });
            document.addEventListener('keydown', closeOnEscape);
        }

        function closeOnEscape(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        }

        function closeModal() {
            clearInterval(slideInterval);
            clearModalVideos();
            if (modal) modal.style.display = 'none';
            document.removeEventListener('keydown', closeOnEscape);
        }

        function attachHoverEvents(photo) {
            photo.addEventListener('mouseenter', () => startSlideShow(photo));
            photo.addEventListener('mouseleave', () => stopSlideShow(photo));
        }

        function buildFetchUrl(afterCursor) {
            const url = new URL(ajaxUrl, window.location.origin);
            url.searchParams.set('action', 'get_instagram_photos');

            if (afterCursor) {
                url.searchParams.set('after', afterCursor);
            }

            if (debugMode) {
                url.searchParams.set('debug', '1');
            }

            return url.toString();
        }

        function formatErrorMessage(errorData) {
            if (!errorData) {
                return 'Instagram feed request failed.';
            }

            if (typeof errorData === 'string') {
                return errorData;
            }

            if (errorData.message) {
                return errorData.message;
            }

            return 'Instagram feed request failed.';
        }

        function showStatus(message, isError, debugData) {
            if (!statusEl || (!debugMode && !isError)) {
                return;
            }

            statusEl.style.display = 'block';
            statusEl.textContent = message;
            statusEl.classList.toggle('is-error', !!isError);

            if (debugMode && debugData) {
                statusEl.textContent += ' ' + JSON.stringify(debugData);
            }
        }

        function hideStatus() {
            if (statusEl) {
                statusEl.style.display = 'none';
                statusEl.textContent = '';
                statusEl.classList.remove('is-error');
            }
        }

        function revealPhotos(newPhotos) {
            newPhotos.forEach(photo => photo.classList.remove('new-photo'));
        }

        function layoutPhotos(newPhotos) {
            if (typeof imagesLoaded === 'function') {
                const revealFallback = setTimeout(() => revealPhotos(newPhotos), 2500);

                imagesLoaded(instagramFeed, function() {
                    clearTimeout(revealFallback);

                    if (typeof Masonry === 'function') {
                        if (!msnry) {
                            msnry = new Masonry(instagramFeed, {
                                itemSelector: '.instagram-photo',
                                columnWidth: '.instagram-photo',
                                percentPosition: true,
                                gutter: 10
                            });
                            instagramFeed.classList.add('masonry-ready');
                        } else {
                            msnry.appended(newPhotos);
                            msnry.layout();
                        }
                    }

                    revealPhotos(newPhotos);
                });

                return;
            }

            if (typeof Masonry === 'function') {
                if (!msnry) {
                    msnry = new Masonry(instagramFeed, {
                        itemSelector: '.instagram-photo',
                        columnWidth: '.instagram-photo',
                        percentPosition: true,
                        gutter: 10
                    });
                    instagramFeed.classList.add('masonry-ready');
                } else {
                    msnry.appended(newPhotos);
                    msnry.layout();
                }
            }

            requestAnimationFrame(() => revealPhotos(newPhotos));
        }

        function updateLoadMoreButton(nextCursor) {
            if (!loadMoreBtn) return;

            if (nextCursor) {
                loadMoreBtn.dataset.after = nextCursor;
                loadMoreBtn.style.display = 'block';
                loadMoreBtn.textContent = defaultButtonText;
            } else {
                loadMoreBtn.dataset.after = '';
                loadMoreBtn.style.display = 'none';
            }
        }

        function fetchInstagramPhotos(afterCursor = '') {
            if (isFetching) return;

            isFetching = true;
            updateDebug({
                fetchStarted: (window.InstaFeedDebug.fetchStarted || 0) + 1,
                lastMessage: 'fetch started'
            });
            hideStatus();
            if (loadMoreBtn) loadMoreBtn.textContent = 'Loading...';

            fetch(buildFetchUrl(afterCursor), { credentials: 'same-origin' })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Instagram AJAX request failed with status ${response.status}`);
                    }

                    return response.json();
                })
                .then(data => {
                    if (!data || !data.success || !data.data || typeof data.data.photos !== 'string') {
                        const err = new Error(formatErrorMessage(data && data.data));
                        err.debugData = data && data.data && data.data.debug ? data.data.debug : null;
                        throw err;
                    }

                    const temp = document.createElement('div');
                    temp.innerHTML = data.data.photos;
                    const newPhotos = Array.from(temp.children).filter(photo => photo.classList && photo.classList.contains('instagram-photo'));

                    newPhotos.forEach(photo => {
                        photo.classList.add('new-photo');
                        instagramFeed.appendChild(photo);
                        attachHoverEvents(photo);
                    });

                    if (newPhotos.length > 0) {
                        layoutPhotos(newPhotos);
                    } else if (instagramFeed.children.length === 0) {
                        showStatus(data.data.message || 'No Instagram posts were returned.', true, data.data.debug);
                        if (loadMoreBtn) {
                            loadMoreBtn.textContent = 'No posts found';
                            loadMoreBtn.style.display = 'block';
                        }
                        return;
                    }

                    updateLoadMoreButton(data.data.next_cursor || '');
                    showStatus(`Loaded ${newPhotos.length} Instagram posts.`, false, data.data.debug);
                })
                .catch(err => {
                    console.error('Error fetching Instagram photos:', err);
                    showStatus(err.message || 'Instagram feed error.', true, err.debugData);
                    if (loadMoreBtn) {
                        loadMoreBtn.textContent = 'Instagram error';
                        loadMoreBtn.style.display = 'block';
                    }
                })
                .finally(() => {
                    isFetching = false;
                });
        }

        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const nextCursor = this.dataset.after || '';

                if (nextCursor || instagramFeed.children.length === 0) {
                    fetchInstagramPhotos(nextCursor);
                }
            });
        }

        if (modalPrevBtn) {
            modalPrevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                moveModalSlide(-1);
                startModalAutoSlide();
            });
        }

        if (modalNextBtn) {
            modalNextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                moveModalSlide(1);
                startModalAutoSlide();
            });
        }

        if (modalCloseBtn) {
            modalCloseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeModal();
            });
        }

        instagramFeed.addEventListener('click', function(e) {
            const photo = e.target.closest('.instagram-photo');
            if (photo) {
                openModal(photo);
            }
        });

        fetchInstagramPhotos();
        return true;
    }

    let booted = false;
    let retryTimer = null;
    let observerTimer = null;
    let observer = null;

    function startRetries() {
        if (retryTimer) {
            return;
        }

        let tries = 0;
        retryTimer = setInterval(() => {
            tries++;
            initAllInstagramFeeds();

            if ((window.InstaFeedDebug.fetchStarted || 0) > 0 || tries >= 40) {
                clearInterval(retryTimer);
                retryTimer = null;
            }
        }, 250);
    }

    function startObserver() {
        if (observer || !window.MutationObserver || !document.body) {
            return;
        }

        observer = new MutationObserver(() => {
            clearTimeout(observerTimer);
            observerTimer = setTimeout(initAllInstagramFeeds, 100);
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function bootInstagramFeed() {
        if (booted) {
            initAllInstagramFeeds();
            return;
        }

        booted = true;
        initAllInstagramFeeds();
        startRetries();
        startObserver();
    }

    onReady(bootInstagramFeed);

    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/global', bootInstagramFeed);
    }
})();
