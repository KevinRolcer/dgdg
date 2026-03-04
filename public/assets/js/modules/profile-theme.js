document.addEventListener('DOMContentLoaded', function () {
    const heroCard = document.getElementById('profileHeroCard');
    if (!heroCard) {
        return;
    }

    const optionButtons = Array.from(document.querySelectorAll('.texture-option'));
    const texturePanel = document.getElementById('profileTexturePanel');
    const openTexturePanelButton = document.querySelector('[data-open-texture-panel]');
    const storageKey = heroCard.dataset.storageKey || 'profile_texture_preference';
    const defaultTexture = heroCard.dataset.defaultTexture || '';
    const passwordModal = document.getElementById('profilePasswordModal');
    const openPasswordModalButton = document.querySelector('[data-open-password-modal]');
    const closePasswordModalButtons = Array.from(document.querySelectorAll('[data-close-password-modal]'));
    const toneClasses = ['texture-tone-verde', 'texture-tone-amarillo', 'texture-tone-rojo', 'texture-tone-blanco'];
    const buttonByUrl = new Map();
    const toneByUrl = new Map();
    let activeButton = null;
    let activeTextureUrl = '';

    optionButtons.forEach(function (button) {
        const textureUrl = button.dataset.textureUrl || '';
        if (!textureUrl) {
            return;
        }

        const textureTone = (button.dataset.textureTone || '').trim();
        buttonByUrl.set(textureUrl, button);
        toneByUrl.set(textureUrl, textureTone);
    });

    const setTexture = function (textureUrl) {
        if (!textureUrl || textureUrl === activeTextureUrl) {
            return;
        }

        activeTextureUrl = textureUrl;
        heroCard.style.backgroundImage = 'url("' + textureUrl + '")';
        heroCard.classList.remove.apply(heroCard.classList, toneClasses);

        const nextActiveButton = buttonByUrl.get(textureUrl) || null;
        if (activeButton && activeButton !== nextActiveButton) {
            activeButton.classList.remove('is-active');
        }
        if (nextActiveButton) {
            nextActiveButton.classList.add('is-active');
            activeButton = nextActiveButton;
        }

        const activeTone = toneByUrl.get(textureUrl) || 'blanco';
        heroCard.classList.add('texture-tone-' + activeTone);

        if (window.localStorage.getItem(storageKey) !== textureUrl) {
            window.localStorage.setItem(storageKey, textureUrl);
        }
    };

    const closeTexturePanel = function () {
        if (!texturePanel) {
            return;
        }

        texturePanel.classList.remove('is-open');
        texturePanel.setAttribute('aria-hidden', 'true');
    };

    const initialTexture = localStorage.getItem(storageKey) || defaultTexture;
    setTexture(initialTexture);

    optionButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const textureUrl = button.dataset.textureUrl || '';
            if (!textureUrl) {
                return;
            }

            setTexture(textureUrl);
            closeTexturePanel();
        });
    });

    if (texturePanel && openTexturePanelButton) {
        openTexturePanelButton.addEventListener('click', function () {
            const willOpen = !texturePanel.classList.contains('is-open');
            texturePanel.classList.toggle('is-open', willOpen);
            texturePanel.setAttribute('aria-hidden', willOpen ? 'false' : 'true');
        });

        document.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            if (!texturePanel.classList.contains('is-open')) {
                return;
            }

            if (texturePanel.contains(target) || openTexturePanelButton.contains(target)) {
                return;
            }

            closeTexturePanel();
        });
    }

    if (passwordModal) {
        const openPasswordModal = function () {
            passwordModal.classList.add('is-open');
            passwordModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        };

        const closePasswordModal = function () {
            passwordModal.classList.remove('is-open');
            passwordModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        if (openPasswordModalButton) {
            openPasswordModalButton.addEventListener('click', openPasswordModal);
        }

        closePasswordModalButtons.forEach(function (button) {
            button.addEventListener('click', closePasswordModal);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && texturePanel && texturePanel.classList.contains('is-open')) {
                closeTexturePanel();
                return;
            }

            if (event.key === 'Escape' && passwordModal.classList.contains('is-open')) {
                closePasswordModal();
            }
        });
    }
});
