// Configuración
const API_BASE = '/api/';
const HEARTBEAT_INTERVAL = 30000;
const POLL_INTERVAL = 60000;
const CLIENT_KEY = localStorage.getItem('client_key') || generateClientKey();

if (!localStorage.getItem('client_key')) {
    localStorage.setItem('client_key', CLIENT_KEY);
}

// Estado
let currentPlaylist = JSON.parse(localStorage.getItem('playlist') || '[]');
let currentVersion = parseInt(localStorage.getItem('playlist_version') || '0');
let currentBackground = localStorage.getItem('background') || '';
let currentOrientation = localStorage.getItem('orientation') || 'horizontal';
let videoIndex = 0;
let isOffline = false;

// DOM
const videoElement = document.getElementById('video-player');
const titleElement = document.getElementById('video-title');
const bgElement = document.getElementById('background');
const statusElement = document.getElementById('status-message');
const statusText = document.getElementById('status-text');

function generateClientKey() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let key = '';
    for (let i = 0; i < 32; i++) key += chars.charAt(Math.floor(Math.random() * chars.length));
    return key;
}

function showStatus(msg, isError = false) {
    statusText.textContent = msg;
    statusElement.classList.remove('hidden');
    statusElement.style.background = isError ? 'rgba(200,0,0,0.8)' : 'rgba(0,0,0,0.8)';
}

function hideStatus() {
    statusElement.classList.add('hidden');
}

function updateBackground(url) {
    if (url) {
        bgElement.style.backgroundImage = `url(${url})`;
        bgElement.style.opacity = '1';
        localStorage.setItem('background', url);
        currentBackground = url;
    } else {
        bgElement.style.opacity = '0';
        currentBackground = '';
        localStorage.removeItem('background');
    }
}

function playNextVideo() {
    if (currentPlaylist.length === 0) {
        showStatus('No hay videos asignados.');
        return;
    }
    const video = currentPlaylist[videoIndex];
    videoElement.src = video.url;
    videoElement.load();
    videoElement.play().catch(e => {
        console.error('Play error:', e);
        setTimeout(playNextVideo, 3000);
    });
    titleElement.textContent = video.title || 'Publicidad';
    titleElement.style.opacity = '1';
    setTimeout(() => titleElement.style.opacity = '0', 5000);
    videoIndex = (videoIndex + 1) % currentPlaylist.length;
}

async function fetchPlaylist(force = false) {
    try {
        const res = await fetch(`${API_BASE}playlist.php?key=${CLIENT_KEY}&v=${currentVersion}`, { cache: 'no-cache' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        if (data.status !== 'ok') throw new Error(data.message || 'Error');

        if (!data.update_required && !force) {
            console.log('Sin cambios - usando caché');
            if (currentPlaylist.length > 0 && (!videoElement.src || videoElement.paused)) {
                playNextVideo();
            }
            hideStatus();
            return;
        }

        console.log('Nueva playlist v' + data.current_version);
        currentPlaylist = data.playlist || [];
        currentVersion = data.current_version;
        currentOrientation = data.orientation || 'horizontal';

        localStorage.setItem('playlist', JSON.stringify(currentPlaylist));
        localStorage.setItem('playlist_version', currentVersion);
        localStorage.setItem('orientation', currentOrientation);

        updateBackground(data.background);
        videoIndex = 0;
        playNextVideo();
        hideStatus();

    } catch (err) {
        console.error('Fetch error:', err);
        isOffline = true;
        showStatus('Offline - usando caché local', true);
        if (currentPlaylist.length > 0 && (!videoElement.src || videoElement.paused)) {
            playNextVideo();
        } else if (currentBackground) {
            updateBackground(currentBackground);
        }
    }
}

async function sendHeartbeat() {
    try {
        const res = await fetch(`${API_BASE}heartbeat.php?key=${CLIENT_KEY}`);
        const data = await res.json();
        if (data.status === 'ok') {
            if (data.background && data.background !== currentBackground) updateBackground(data.background);
            if (data.orientation && data.orientation !== currentOrientation) {
                currentOrientation = data.orientation;
                localStorage.setItem('orientation', currentOrientation);
            }
            isOffline = false;
        }
    } catch {}
}

videoElement.addEventListener('ended', () => {
    playNextVideo();
    if (!isOffline) fetchPlaylist();
});

videoElement.addEventListener('error', () => {
    setTimeout(playNextVideo, 2000);
});

async function init() {
    if (document.documentElement.requestFullscreen) {
        document.documentElement.requestFullscreen().catch(() => {});
    }

    if (currentPlaylist.length > 0) {
        playNextVideo();
        hideStatus();
    } else {
        showStatus('Cargando...');
    }

    await sendHeartbeat();
    await fetchPlaylist(true);

    setInterval(sendHeartbeat, HEARTBEAT_INTERVAL);
    setInterval(fetchPlaylist, POLL_INTERVAL);
}

init();
