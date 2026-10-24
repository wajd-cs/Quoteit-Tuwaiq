const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const QI = window.QI || { t: {} };
const t = (key, fallback) => QI.t?.[key] || fallback;
const root = document.documentElement;
const reduceMotion = () => root.dataset.motion === 'reduce' || window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// toast
let toastTimer;
function toast(message) {
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = message;
    el.dataset.show = 'true';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (el.dataset.show = 'false'), 2600);
}

function showBadges(names) {
    (names || []).forEach((name, i) => setTimeout(() => toast(`${t('new_badge', 'New badge')}: ${name}`), 600 + i * 2800));
}

// badges earned on the last page load
document.querySelectorAll('[data-new-badges]').forEach((el) => {
    try {
        showBadges(JSON.parse(el.dataset.newBadges));
    } catch (e) {}
});

// small "pressed" glow on every button, so each click feels answered
document.addEventListener('click', (event) => {
    const button = event.target.closest('button, .btn-primary, .btn-secondary, .btn-ghost, .chip, .tab, .action');
    if (!button || reduceMotion()) return;
    button.classList.remove('did-press');
    void button.offsetWidth; // restart the animation
    button.classList.add('did-press');
});

// like / save / follow without reloading
document.addEventListener('submit', async (event) => {
    const form = event.target;

    // Confirm before deleting.
    if (form.dataset.confirm && !confirm(form.dataset.confirm)) {
        event.preventDefault();
        return;
    }

    const kind = form.dataset.toggle;
    if (!kind) {
        // normal forms: show they are working and stop double clicks
        if (!form.matches('[data-gets-me], [data-pick], [data-prefs-form], [data-studio-form]') && form.method.toLowerCase() === 'post') {
            const submitter = event.submitter || form.querySelector('[type=submit]');
            if (submitter) {
                setTimeout(() => {
                    submitter.setAttribute('aria-busy', 'true');
                    submitter.disabled = true;
                }, 0);
            }
        }
        return;
    }

    event.preventDefault();
    const button = form.querySelector('button');
    button.disabled = true;

    // answer the click straight away, the server confirms after
    if (kind === 'like') {
        const willLike = button.getAttribute('aria-pressed') !== 'true';
        if (willLike && !reduceMotion()) {
            button.classList.remove('heart-pop');
            void button.offsetWidth;
            button.classList.add('heart-pop');
        }
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        });
        if (!response.ok) throw new Error(response.status);
        const data = await response.json();

        // same quote can show twice (quote of the day)
        document.querySelectorAll(`form[data-toggle="${kind}"][action="${form.action}"] button`).forEach((btn) => {
            btn.setAttribute('aria-pressed', data.active ? 'true' : 'false');
            const count = btn.querySelector('[data-count]');
            if (count && data.count !== undefined) count.textContent = data.count;
            const label = btn.querySelector('[data-label]');
            if (label) label.textContent = data.active ? btn.dataset.on : btn.dataset.off;
        });

        if (kind === 'bookmark') toast(data.active ? t('saved_fav', 'Saved to favourites') : t('removed_fav', 'Removed from favourites'));
        if (kind === 'follow') {
            const followers = document.querySelector('[data-followers-count]');
            if (followers && data.count !== undefined) followers.textContent = data.count;
        }
        showBadges(data.badges);
    } catch (error) {
        form.submit();
    } finally {
        button.disabled = false;
    }
});

// copy
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;
    try {
        await navigator.clipboard.writeText(button.dataset.copy);
        toast(t('copied', 'Copied'));
    } catch (error) {
        toast(t('copy_failed', 'Could not copy'));
    }
});

// read aloud (for people with low vision)
// the speed is saved like the other accessibility settings and can be changed from the bar while listening
const speechBar = document.getElementById('speech-bar');
let speakingButton = null;
let speechRun = 0;

function markRate() {
    const rate = parseFloat(root.dataset.speechRate) || 1;
    speechBar?.querySelectorAll('[data-rate]').forEach((chip) => {
        chip.setAttribute('aria-pressed', parseFloat(chip.dataset.rate) === rate ? 'true' : 'false');
    });
}

function stopSpeaking() {
    speechRun++;
    window.speechSynthesis?.cancel();
    speakingButton?.classList.remove('is-speaking');
    speakingButton?.setAttribute('aria-pressed', 'false');
    speakingButton = null;
    if (speechBar) speechBar.hidden = true;
    delete root.dataset.speaking;
}

function speak(button) {
    stopSpeaking();
    const run = speechRun;

    const utterance = new SpeechSynthesisUtterance(button.dataset.speak);
    utterance.lang = button.dataset.lang || 'en-US';
    utterance.rate = parseFloat(root.dataset.speechRate) || 1;
    const voice = window.speechSynthesis.getVoices().find((v) => v.lang.startsWith(utterance.lang.slice(0, 2)));
    if (voice) utterance.voice = voice;
    utterance.onend = utterance.onerror = () => {
        if (run === speechRun) stopSpeaking();
    };

    speakingButton = button;
    button.classList.add('is-speaking');
    button.setAttribute('aria-pressed', 'true');
    if (speechBar) {
        speechBar.querySelector('[data-speech-text]').textContent = button.dataset.speak;
        speechBar.hidden = false;
        root.dataset.speaking = '1';
        markRate();
    }
    window.speechSynthesis.speak(utterance);
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-speak]');
    if (!button) return;

    if (!('speechSynthesis' in window)) {
        toast(t('no_speech', 'Your browser cannot read text aloud.'));
        return;
    }

    if (speakingButton === button) {
        stopSpeaking(); // second press = stop
        return;
    }
    speak(button);
});

speechBar?.querySelector('[data-speech-stop]').addEventListener('click', stopSpeaking);

speechBar?.querySelectorAll('[data-rate]').forEach((chip) => {
    chip.addEventListener('click', () => {
        savePrefs({ speech_rate: parseFloat(chip.dataset.rate) });
        markRate();
        // keep the accessibility page in step if it is open
        document.querySelectorAll('form[data-prefs-form] [name="speech_rate"]').forEach((radio) => {
            radio.checked = parseFloat(radio.value) === parseFloat(chip.dataset.rate);
        });
        // start the same quote again at the new speed
        if (speakingButton) speak(speakingButton);
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && speakingButton) stopSpeaking();
});

// native share sheet (phones)
document.querySelectorAll('[data-native-share]').forEach((button) => {
    if (!navigator.share) return;
    button.classList.remove('hidden');
    button.addEventListener('click', () => {
        navigator.share({ title: button.dataset.title, text: button.dataset.text, url: button.dataset.url }).catch(() => {});
    });
});

// ---------- display settings (accessibility center) ----------
const PREF_KEYS = ['contrast', 'colors', 'text_scale', 'speech_rate', 'reduce_motion', 'underline_links', 'readable_spacing'];

function applyPrefs(prefs) {
    if (prefs.contrast) root.dataset.contrast = prefs.contrast;
    if (prefs.colors) root.dataset.colors = prefs.colors;
    if ('reduce_motion' in prefs) root.dataset.motion = prefs.reduce_motion ? 'reduce' : 'full';
    if ('underline_links' in prefs) root.dataset.links = prefs.underline_links ? 'underline' : 'plain';
    if ('readable_spacing' in prefs) root.dataset.spacing = prefs.readable_spacing ? 'wide' : 'normal';
    if (prefs.speech_rate) root.dataset.speechRate = prefs.speech_rate;
    if (prefs.theme) root.dataset.theme = prefs.theme;
    if (prefs.text_scale) root.style.setProperty('--qscale', prefs.text_scale);
}

let saveTimer;
function savePrefs(changes) {
    applyPrefs(changes);

    // guests: this browser only
    try {
        const stored = JSON.parse(localStorage.getItem('qi-prefs') || '{}');
        localStorage.setItem('qi-prefs', JSON.stringify({ ...stored, ...changes }));
    } catch (e) {}

    if (root.dataset.signedIn !== '1') {
        markSaved();
        return;
    }

    // signed in: save to the account (a short wait groups quick changes together)
    clearTimeout(saveTimer);
    saveTimer = setTimeout(async () => {
        try {
            const response = await fetch(QI.savePrefsUrl, {
                method: 'PUT',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(changes),
            });
            if (!response.ok) throw new Error(response.status);
            markSaved();
        } catch (e) {
            toast(t('save_failed', 'Could not save. Please try again.'));
        }
    }, 250);
}

function markSaved() {
    const status = document.querySelector('[data-prefs-status]');
    if (status) status.textContent = t('settings_saved', 'Settings saved');
    const badge = document.querySelector('[data-prefs-saved]');
    if (badge) {
        badge.hidden = false;
        clearTimeout(badge._t);
        badge._t = setTimeout(() => (badge.hidden = true), 2000);
    }
}

const prefsForm = document.querySelector('form[data-prefs-form]');
if (prefsForm) {
    const read = () => {
        const prefs = {};
        PREF_KEYS.forEach((key) => {
            const fields = prefsForm.querySelectorAll(`[name="${key}"]`);
            fields.forEach((field) => {
                if (field.type === 'radio' && field.checked) prefs[key] = field.value;
                if (field.type === 'checkbox') prefs[key] = field.checked;
                if (field.tagName === 'SELECT') prefs[key] = field.value;
            });
        });
        return prefs;
    };

    prefsForm.addEventListener('change', () => savePrefs(read()));
    prefsForm.addEventListener('submit', (event) => {
        event.preventDefault();
        savePrefs(read());
    });
    prefsForm.querySelector('[data-prefs-reset]')?.addEventListener('click', () => {
        const defaults = { contrast: 'normal', colors: 'default', text_scale: 1, speech_rate: 1, reduce_motion: false, underline_links: false, readable_spacing: false };
        Object.entries(defaults).forEach(([key, value]) => {
            prefsForm.querySelectorAll(`[name="${key}"]`).forEach((field) => {
                if (field.type === 'radio') field.checked = String(field.value) === String(value);
                if (field.type === 'checkbox') field.checked = value;
                if (field.tagName === 'SELECT') field.value = String(value);
            });
        });
        savePrefs(defaults);
    });
}

// text size (a− / a+), saved like the other settings
const SCALES = [0.85, 1, 1.15, 1.3, 1.5, 1.75];
function currentScale() {
    return parseFloat(getComputedStyle(root).getPropertyValue('--qscale')) || 1;
}
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-text-size]');
    if (!button) return;
    const now = currentScale();
    let index = SCALES.findIndex((s) => Math.abs(s - now) < 0.01);
    if (index === -1) index = 1;
    index = Math.max(0, Math.min(SCALES.length - 1, index + (button.dataset.textSize === 'up' ? 1 : -1)));
    savePrefs({ text_scale: SCALES[index] });
    const select = document.querySelector('form[data-prefs-form] [name="text_scale"]');
    if (select) select.value = String(SCALES[index]);
    toast(`${t('text_size', 'Text size')} ${Math.round(SCALES[index] * 100)}%`);
});

// keyboard: "/" jumps to the search box
document.addEventListener('keydown', (event) => {
    if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
    if (event.target.closest('input, textarea, select, [contenteditable]')) return;
    const search = document.querySelector('[data-search-shortcut]');
    if (search) {
        event.preventDefault();
        search.focus();
    }
});

// character counters
document.querySelectorAll('[data-counter]').forEach((field) => {
    const output = field.parentElement.querySelector('[data-counter-output]');
    if (!output) return;
    const update = () => (output.textContent = `${field.value.length}/${field.maxLength}`);
    field.addEventListener('input', update);
    update();
});

// re-open a form that came back with errors
document.querySelectorAll('[data-open-popover]').forEach((marker) => {
    document.getElementById(marker.dataset.openPopover)?.showPopover?.();
});

// "publish later": the button says Schedule when a time is chosen
document.querySelectorAll('[data-schedule-input]').forEach((input) => {
    const button = input.form?.querySelector('[data-schedule-label]');
    if (!button) return;
    const update = () => (button.textContent = input.value ? button.dataset.scheduleLabel : button.dataset.submitLabel);
    input.addEventListener('input', update);
    update();
});

// count copies and shares (for "most shared"), sent in the background
function recordShare(url, channel) {
    if (!url || !channel) return;
    const body = new FormData();
    body.append('channel', channel);
    fetch(url, { method: 'POST', body, keepalive: true, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } }).catch(() => {});
}
document.addEventListener('click', (event) => {
    const el = event.target.closest('[data-share-channel]');
    if (el) recordShare(el.dataset.shareUrl, el.dataset.shareChannel);
});

// profile picture preview
document.querySelectorAll('[data-avatar-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        const preview = document.querySelector('[data-avatar-preview]');
        if (!file || !preview) return;
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
    });
});

// insights: show date fields only for "custom dates"
document.querySelectorAll('[data-range-select]').forEach((select) => {
    const toggle = () => document.querySelectorAll('[data-custom-dates]').forEach((el) => (el.hidden = select.value !== 'custom'));
    select.addEventListener('change', toggle);
});

// ---------- quote that gets me ----------
const getsMeForm = document.querySelector('form[data-gets-me]');
if (getsMeForm) {
    const results = document.querySelector('[data-gets-me-results]');
    const skeleton = document.querySelector('template[data-gets-me-skeleton]');
    const errorBox = getsMeForm.querySelector('[data-gets-me-error]');
    const submit = getsMeForm.querySelector('[data-gets-me-submit]');
    let seen = [];
    let lastChoice = '';

    const showError = (message) => {
        errorBox.textContent = message;
        errorBox.hidden = !message;
    };

    const setBusy = (busy) => {
        submit.disabled = busy;
        submit.querySelector('[data-idle]').hidden = busy;
        const busyLabel = submit.querySelector('[data-busy]');
        busyLabel.hidden = !busy;
        busyLabel.style.display = busy ? 'inline-flex' : '';
        results.setAttribute('aria-busy', busy ? 'true' : 'false');
        document.querySelectorAll('[data-gets-me-more]').forEach((b) => (b.disabled = busy));
    };

    const loadSuggestions = async () => {
        const data = new FormData(getsMeForm);
        const mood = data.get('mood');
        const goal = data.get('goal');

        if (!mood || !goal) {
            showError(!mood ? t('choose_mood', 'Choose how you feel first.') : t('choose_goal', 'Choose what you need right now.'));
            return;
        }
        showError('');

        const choice = `${mood}|${goal}`;
        if (choice !== lastChoice) seen = [];
        lastChoice = choice;

        const params = new URLSearchParams({ mood, goal });
        seen.forEach((id) => params.append('except[]', id));

        setBusy(true);
        results.innerHTML = skeleton.innerHTML;

        try {
            const response = await fetch(`${getsMeForm.action}?${params}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (response.status === 422) {
                const body = await response.json();
                results.innerHTML = '';
                showError(Object.values(body.errors || {}).flat().join(' ') || t('check_choices', 'Please check your choices.'));
                return;
            }
            if (response.status === 401 || response.status === 419) {
                window.location.reload(); // session ended, let the server send them to log in
                return;
            }
            if (!response.ok) throw new Error(response.status);

            const body = await response.json();
            results.innerHTML = body.html;
            seen.push(...body.ids);
            history.replaceState(null, '', `${getsMeForm.action}?mood=${mood}&goal=${goal}`);
            results.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'start' });
        } catch (error) {
            results.innerHTML = '';
            showError(t('load_failed', 'Something went wrong while loading quotes. Check your connection and try again.'));
        } finally {
            setBusy(false);
        }
    };

    getsMeForm.addEventListener('submit', (event) => {
        event.preventDefault();
        loadSuggestions();
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-gets-me-more]')) loadSuggestions();
    });

    // the page was opened from a link with ?mood=&goal= (results already there)
    document.querySelectorAll('[data-suggested-id]').forEach((card) => seen.push(Number(card.dataset.suggestedId)));
    const initial = new FormData(getsMeForm);
    if (initial.get('mood') && initial.get('goal')) lastChoice = `${initial.get('mood')}|${initial.get('goal')}`;
}

// "this gets me" -> save to history
document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!form.matches('form[data-pick]')) return;
    event.preventDefault();

    const button = form.querySelector('button');
    if (button.disabled) return;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        });
        if (!response.ok) throw new Error(response.status);
        const data = await response.json();
        button.textContent = button.dataset.labelDone;
        toast(t('added_history', 'Added to your history'));
        showBadges(data.badges);
    } catch (error) {
        button.disabled = false;
        toast(t('save_failed', 'Could not save. Please try again.'));
    } finally {
        button.removeAttribute('aria-busy');
    }
});

// ---------- quote design studio ----------
const studio = document.querySelector('[data-studio]');
if (studio) {
    const config = JSON.parse(studio.dataset.config);
    const canvas = studio.querySelector('[data-studio-canvas]');
    const ctx = canvas.getContext('2d');
    const form = studio.querySelector('[data-studio-form]');
    const SIZES = { square: [1080, 1080], story: [1080, 1920], wide: [1200, 630] };
    const loadedFonts = new Set(['Almarai', 'Readex Pro']);

    const value = (name) => {
        const field = form.elements[name];
        if (!field) return null;
        if (field instanceof RadioNodeList) return field.value;
        return field.type === 'checkbox' ? field.checked : field.value;
    };

    const applyTemplate = (key) => {
        const tpl = config.templates[key];
        if (!tpl) return;
        form.elements.bg1.value = tpl.bg[0];
        form.elements.bg2.value = tpl.bg[1];
        form.elements.text.value = tpl.text;
        form.elements.accent.value = tpl.accent;
    };

    async function ensureFont(name) {
        if (loadedFonts.has(name) || !config.fonts[name]) return;
        try {
            const face = new FontFace(name, `url(${config.fonts[name]})`, { weight: '100 900' });
            document.fonts.add(await face.load());
            loadedFonts.add(name);
        } catch (e) {}
    }

    function wrapLines(text, maxWidth) {
        const words = text.split(/\s+/);
        const lines = [];
        let line = '';
        words.forEach((word) => {
            const test = line ? `${line} ${word}` : word;
            if (ctx.measureText(test).width > maxWidth && line) {
                lines.push(line);
                line = word;
            } else {
                line = test;
            }
        });
        if (line) lines.push(line);
        return lines;
    }

    async function draw() {
        const [w, h] = SIZES[value('size')] || SIZES.square;
        canvas.width = w;
        canvas.height = h;
        const font = value('font');
        await ensureFont(font);
        await document.fonts.load(`700 40px "${font}"`).catch(() => {});

        const family = `"${font}", "Almarai", "Readex Pro", sans-serif`;
        const scale = Number(value('fontSize')) / 100;
        studio.querySelector('[data-studio-size-out]').textContent = `${value('fontSize')}%`;

        // background
        const gradient = ctx.createLinearGradient(0, 0, w, h);
        gradient.addColorStop(0, value('bg1'));
        gradient.addColorStop(1, value('bg2'));
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, w, h);

        // soft circle decoration
        ctx.globalAlpha = 0.12;
        ctx.fillStyle = value('accent');
        ctx.beginPath();
        ctx.arc(w * (config.rtl ? 0.12 : 0.88), h * 0.12, Math.min(w, h) * 0.28, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalAlpha = 1;

        const pad = Math.round(w * 0.1);
        const maxWidth = w - pad * 2;
        const align = value('align');
        ctx.direction = config.rtl ? 'rtl' : 'ltr';
        ctx.textAlign = align === 'center' ? 'center' : 'start';
        const x = align === 'center' ? w / 2 : config.rtl ? w - pad : pad;

        // fit the text: shrink until it fits in 70% of the height
        let size = Math.round((w / 15) * scale);
        let lines;
        let lineHeight;
        for (let i = 0; i < 30; i++) {
            ctx.font = `700 ${size}px ${family}`;
            lineHeight = size * (config.rtl ? 1.7 : 1.4);
            lines = wrapLines(config.text, maxWidth);
            if (lines.length * lineHeight <= h * 0.62 || size < 22) break;
            size = Math.round(size * 0.92);
        }

        const authorSize = Math.round(size * 0.5);
        const blockHeight = lines.length * lineHeight + (value('showAuthor') ? authorSize * 2.6 : 0);
        let y = (h - blockHeight) / 2 + lineHeight * 0.75;

        if (value('showMarks')) {
            ctx.save();
            ctx.fillStyle = value('accent');
            ctx.globalAlpha = 0.9;
            ctx.font = `700 ${Math.round(size * 2.6)}px Georgia, serif`;
            ctx.textAlign = 'center';
            ctx.fillText(config.rtl ? '”' : '“', align === 'center' ? w / 2 : x + (config.rtl ? -size * 0.5 : size * 0.5), y - lineHeight * 0.55);
            ctx.restore();
        }

        ctx.fillStyle = value('text');
        ctx.font = `700 ${size}px ${family}`;
        lines.forEach((line) => {
            ctx.fillText(line, x, y);
            y += lineHeight;
        });

        if (value('showAuthor') && config.author) {
            ctx.fillStyle = value('accent');
            ctx.font = `400 ${authorSize}px ${family}`;
            ctx.fillText(`— ${config.author}`, x, y + authorSize * 0.6);
        }

        if (value('showBrand')) {
            ctx.fillStyle = value('text');
            ctx.globalAlpha = 0.6;
            ctx.font = `700 ${Math.round(w / 40)}px Georgia, serif`;
            ctx.textAlign = 'center';
            ctx.direction = 'ltr';
            ctx.fillText('QuoteIt', w / 2, h - Math.round(h * 0.05));
            ctx.globalAlpha = 1;
        }
    }

    const toBlob = () => new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));

    const recordDesign = async () => {
        if (!config.designedUrl) return;
        try {
            const response = await fetch(config.designedUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ template: value('template') }),
            });
            const data = await response.json();
            showBadges(data.badges);
        } catch (e) {}
    };

    const download = async () => {
        const blob = await toBlob();
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = config.fileName;
        document.body.appendChild(link);
        link.click();
        link.remove();
        toast(t('image_saved', 'Image saved'));
        recordDesign();
        recordShare(config.sharedUrl, 'image');
    };

    studio.querySelector('[data-studio-download]').addEventListener('click', download);
    studio.querySelector('[data-studio-share]').addEventListener('click', async () => {
        const blob = await toBlob();
        const file = new File([blob], config.fileName, { type: 'image/png' });
        if (navigator.canShare?.({ files: [file] })) {
            try {
                await navigator.share({ files: [file], text: `${config.shareUrl}` });
                recordDesign();
                recordShare(config.sharedUrl, 'image');
                return;
            } catch (e) {
                if (e.name === 'AbortError') return;
            }
        }
        await download();
    });

    form.addEventListener('change', (event) => {
        if (event.target.name === 'template') applyTemplate(event.target.value);
        draw();
    });
    form.addEventListener('input', (event) => {
        if (event.target.type === 'color' || event.target.type === 'range') draw();
    });

    applyTemplate(value('template'));
    draw();
}

// light / dark mode (saved with the other display settings)
document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    if (root.dataset.theme === 'light') {
        button.setAttribute('aria-label', t('to_dark', 'Switch to dark mode'));
        button.title = t('to_dark', 'Switch to dark mode');
    }
    button.addEventListener('click', () => {
        const theme = root.dataset.theme === 'light' ? 'dark' : 'light';
        savePrefs({ theme });
        const label = theme === 'light' ? t('to_dark', 'Switch to dark mode') : t('to_light', 'Switch to light mode');
        button.setAttribute('aria-label', label);
        button.title = label;
    });
});
