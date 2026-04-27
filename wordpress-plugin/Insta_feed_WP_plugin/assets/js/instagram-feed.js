(function() {
    window.addEventListener('load', function() {
        const ajaxUrl = (typeof Insta_feed_WP_plugin !== 'undefined' && Insta_feed_WP_plugin.ajax_url)
            ? Insta_feed_WP_plugin.ajax_url
            : '/wp-admin/admin-ajax.php';

        const feeds = document.querySelectorAll('.Insta_feed_WP_plugin-feed-wrap');

        if (feeds.length > 0) {
            feeds.forEach(feedWrap => initInstagramFeed(feedWrap, ajaxUrl));
            return;
        }

        const legacyFeed = document.getElementById('instagram-feed');
        if (legacyFeed) {
            initInstagramFeed(document, ajaxUrl);
        }
    });

    function initInstagramFeed(root, ajaxUrl) {
        const instagramFeed = root.querySelector('.Insta_feed_WP_plugin-feed') || root.querySelector('#instagram-feed');
        if (!instagramFeed) {
            return;
        }

        const loadMoreBtn = root.querySelector('.Insta_feed_WP_plugin-load-more') || root.querySelector('#load-more-instagram');
        const modal = root.querySelector('.Insta_feed_WP_plugin-modal') || root.querySelector('#image-modal');
        const modalOverlay = root.querySelector('.Insta_feed_WP_plugin-modal-overlay') || root.querySelector('#modal-overlay');
        const modalImageOld = root.querySelector('.Insta_feed_WP_plugin-modal-image-old') || root.querySelector('#modal-image-old');
        const modalImageNew = root.querySelector('.Insta_feed_WP_plugin-modal-image-new') || root.querySelector('#modal-image-new');

        let currentIntervals = new Map();
        let currentSlides = [];
        let currentIndex = 0;
        let slideInterval;
        let msnry = null;
        let isFetching = false;
        const defaultButtonText = loadMoreBtn ? loadMoreBtn.textContent : 'Show More';

        function slideImages(container, nextSrc) {
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
            const slides = JSON.parse(photo.getAttribute('data-slides') || '[]');
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
            const slides = JSON.parse(photo.getAttribute('data-slides') || '[]');
            if (slides.length > 0) {
                const container = photo.querySelector('.slide-container');
                const oldImg = container.querySelector('.slide-img-old');
                const newImg = container.querySelector('.slide-img-new');
                oldImg.src = slides[0].thumb;
                oldImg.style.transform = 'translateX(0)';
                newImg.style.transform = 'translateX(100%)';
            }
        }

        function openModal(photo) {
            currentSlides = JSON.parse(photo.getAttribute('data-slides') || '[]');
            if (currentSlides.length === 0 || !modal || !modalOverlay || !modalImageOld) return;

            currentIndex = 0;
            modalImageOld.src = currentSlides[currentIndex].full;
            modal.style.display = 'flex';
            clearInterval(slideInterval);
            slideInterval = setInterval(() => {
                currentIndex = (currentIndex + 1) % currentSlides.length;
                slideImagesModal(currentSlides[currentIndex].full);
            }, 2000);

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
            if (modal) modal.style.display = 'none';
            document.removeEventListener('keydown', closeOnEscape);
        }

        function attachHoverEvents(photo) {
            photo.addEventListener('mouseenter', () => startSlideShow(photo));
            photo.addEventListener('mouseleave', () => stopSlideShow(photo));
        }

        function fetchInstagramPhotos(afterCursor = '') {
            if (isFetching) return;
            isFetching = true;
            if (loadMoreBtn) loadMoreBtn.textContent = 'Loading...';

            fetch(`${ajaxUrl}?action=get_instagram_photos&after=${encodeURIComponent(afterCursor)}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.data.photos) {
                        if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                        return;
                    }

                    const temp = document.createElement('div');
                    temp.innerHTML = data.data.photos;
                    const newPhotos = Array.from(temp.children);

                    newPhotos.forEach(photo => {
                        photo.classList.add('new-photo');
                        instagramFeed.appendChild(photo);
                        attachHoverEvents(photo);
                    });

                    if (typeof imagesLoaded === 'function') {
                        imagesLoaded(instagramFeed, function() {
                            if (!msnry && typeof Masonry === 'function') {
                                msnry = new Masonry(instagramFeed, {
                                    itemSelector: '.instagram-photo',
                                    columnWidth: '.instagram-photo',
                                    percentPosition: true,
                                    gutter: 10
                                });
                            } else if (msnry) {
                                msnry.appended(newPhotos);
                                msnry.layout();
                            }
                            newPhotos.forEach(photo => photo.classList.remove('new-photo'));
                        });
                    }

                    if (loadMoreBtn) {
                        if (data.data.next_cursor) {
                            loadMoreBtn.dataset.after = data.data.next_cursor;
                            loadMoreBtn.style.display = 'block';
                            loadMoreBtn.textContent = defaultButtonText || 'Show More';
                        } else {
                            loadMoreBtn.style.display = 'none';
                        }
                    }
                })
                .catch(err => {
                    console.error('Error fetching Instagram photos:', err);
                    if (loadMoreBtn) loadMoreBtn.textContent = 'Error!';
                })
                .finally(() => {
                    isFetching = false;
                });
        }

        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const nextCursor = this.dataset.after;
                if (nextCursor) fetchInstagramPhotos(nextCursor);
            });
        }

        instagramFeed.addEventListener('click', function(e) {
            const photo = e.target.closest('.instagram-photo');
            if (photo) {
                openModal(photo);
            }
        });

        fetchInstagramPhotos();
    }
})();
