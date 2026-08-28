document.addEventListener('DOMContentLoaded', () => {
    // UI Elements
    const connectBtn = document.getElementById('mvx-connect-trigger');
    const modalBackdrop = document.getElementById('mvx-wallet-modal-backdrop');
    const modalClose = document.getElementById('mvx-modal-close-btn');
    
    const walletOpts = document.getElementById('mvx-wallet-options-list');
    const qrContainer = document.getElementById('mvx-qr-code-holder');
    const statusContainer = document.getElementById('mvx-status-holder');
    const statusMsg = document.getElementById('mvx-status-message');
    
    // Wallet selection buttons
    const btnExtension = document.getElementById('mvx-btn-extension');
    const btnXportal = document.getElementById('mvx-btn-xportal');
    const btnWeb = document.getElementById('mvx-btn-web');
    const btnXalias = document.getElementById('mvx-btn-xalias');
    
    let elvenInitialized = false;
    let ElvenJS = null;

    if (!connectBtn) return; // Exit if gater not present on page

    // Open Modal
    connectBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        showModal();
        if (!elvenInitialized) {
            await initElven();
        }
    });

    // Close Modal
    modalClose.addEventListener('click', closeModal);
    modalBackdrop.addEventListener('click', (e) => {
        if (e.target === modalBackdrop) {
            closeModal();
        }
    });

    function showModal() {
        modalBackdrop.classList.add('is-active');
        resetUI();
    }

    function closeModal() {
        modalBackdrop.classList.remove('is-active');
    }

    function resetUI() {
        walletOpts.style.display = 'flex';
        qrContainer.classList.remove('is-active');
        statusContainer.classList.remove('is-active');
    }

    function showLoading(message) {
        walletOpts.style.display = 'none';
        qrContainer.classList.remove('is-active');
        statusContainer.classList.add('is-active');
        statusMsg.textContent = message;
    }

    function showQRState() {
        walletOpts.style.display = 'none';
        statusContainer.classList.remove('is-active');
        qrContainer.classList.add('is-active');
    }

    // Dynamic import and init of Elven.js
    async function initElven() {
        showLoading('Chargement de la bibliothèque MultiversX...');
        try {
            // Import elven.js from CDN
            const module = await import('https://unpkg.com/elven.js/build/elven.js');
            ElvenJS = module.ElvenJS;
            
            await ElvenJS.init({
                apiUrl: mvxGaterSettings.apiUrl,
                chainType: mvxGaterSettings.chainType,
                walletConnectV2ProjectId: mvxGaterSettings.wcProjectId,
                walletConnectV2RelayAddresses: ['wss://relay.walletconnect.com'],
                onLoginSuccess: async (address) => {
                    await handleLoginSuccess(address);
                },
                onLoginFailure: (error) => {
                    showToast(error || 'Connexion échouée.', true);
                    resetUI();
                },
                onLogoutSuccess: () => {
                    console.log('ElvenJS Logged Out.');
                },
                onQrPending: () => {
                    console.log('WalletConnect QR Code is pending generation...');
                },
                onQrLoaded: () => {
                    console.log('WalletConnect QR Code has loaded!');
                }
            });
            
            elvenInitialized = true;
            setupWalletListeners();
            resetUI();
        } catch (err) {
            console.error('Failed to init ElvenJS:', err);
            showToast('Erreur d\'initialisation du kit SDK.', true);
            closeModal();
        }
    }

    function setupWalletListeners() {
        // DeFi Wallet extension
        btnExtension.addEventListener('click', () => {
            showLoading('Connexion à DeFi Wallet Extension...');
            ElvenJS.login('browser-extension');
        });

        // xPortal Mobile
        btnXportal.addEventListener('click', () => {
            if (!mvxGaterSettings.wcProjectId) {
                showToast("Erreur: ID de projet WalletConnect manquant dans les réglages admin du plugin.", true);
                resetUI();
                return;
            }
            showQRState();
            ElvenJS.login('mobile', {
                qrCodeContainer: document.getElementById('mvx-qr-canvas-container')
            });
        });

        // Web Wallet
        btnWeb.addEventListener('click', () => {
            showLoading('Redirection vers Web Wallet...');
            ElvenJS.login('web-wallet');
        });

        // xAlias
        btnXalias.addEventListener('click', () => {
            showLoading('Connexion à xAlias (Google)...');
            ElvenJS.login('x-alias');
        });
    }

    // Post-authentication Challenge-Response flow
    async function handleLoginSuccess(addrParam) {
        showLoading('Authentification cryptographique...');
        try {
            // Get the address from ElvenJS storage if parameter is undefined
            const address = addrParam || ElvenJS.storage.get('address');
            if (!address) {
                throw new Error("Impossible de récupérer l'adresse de portefeuille.");
            }

            // 1. Get challenge from server
            statusMsg.textContent = 'Génération du défi sécurisé...';
            const challengeResponse = await fetch(`${mvxGaterSettings.restUrl}challenge?address=${address}&_=${Date.now()}`, {
                credentials: 'same-origin'
            });
            if (!challengeResponse.ok) {
                throw new Error('Impossible de récupérer le défi serveur.');
            }
            const challengeData = await challengeResponse.json();
            console.log('Challenge data received from server:', challengeData);
            const challenge = challengeData.challenge;
            const challengeId = challengeData.challenge_id;

            // 2. Ask user to sign message
            statusMsg.textContent = 'Veuillez signer le message dans votre wallet...';
            const signResult = await ElvenJS.signMessage(challenge);

            if (!signResult || !signResult.messageSignature) {
                throw new Error('Signature annulée ou invalide.');
            }

            const signature = signResult.messageSignature;

            // Extract post_id from overlay DOM element if present
            const overlayEl = document.querySelector('.mvx-gater-overlay');
            const postId = overlayEl && overlayEl.dataset.postId ? parseInt(overlayEl.dataset.postId, 10) : 0;

            // 3. Verify signature on server & check NFT
            statusMsg.textContent = 'Vérification de la signature et des actifs...';
            const verifyResponse = await fetch(`${mvxGaterSettings.restUrl}verify`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': mvxGaterSettings.wpNonce
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    address: address,
                    signature: signature,
                    challenge_id: challengeId,
                    post_id: postId
                })
            });

            const verifyData = await verifyResponse.json();
            console.log('Verify response received from server:', verifyData);

            if (verifyData.success) {
                showToast('Accès autorisé ! Rechargement...', false);
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast(verifyData.message || 'Accès refusé.', true);
                await ElvenJS.logout();
                resetUI();
            }

        } catch (err) {
            console.error('Auth flow failed:', err);
            showToast(err.message || 'Échec de l\'authentification.', true);
            try {
                await ElvenJS.logout();
            } catch (e) {}
            resetUI();
        }
    }

    // Toast alerts helper
    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'mvx-toast';
        if (!isError) {
            toast.classList.add('mvx-toast-success');
        }
        
        // Add lock icon or alert icon
        const icon = isError ? '⚠️' : '🔓';
        toast.innerHTML = `<span>${icon}</span> <span>${message}</span>`;
        
        document.body.appendChild(toast);
        
        // Trigger reflow for transition
        toast.offsetHeight;
        toast.classList.add('is-active');
        
        setTimeout(() => {
            toast.classList.remove('is-active');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 4000);
    }
});
