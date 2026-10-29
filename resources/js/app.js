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
        if (!form.matches('[data-gets-me], [data-pick], [data-prefs-form], [data-studio-form], [data-chat-form]') && form.method.toLowerCase() === 'post') {
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
    // same quote can show twice (quote of the day), so update every copy
    const buttons = [...document.querySelectorAll(`form[data-toggle="${kind}"][action="${form.action}"] button`)];
    const wasOn = button.getAttribute('aria-pressed') === 'true';

    const paint = (on, count) => buttons.forEach((btn) => {
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        const counter = btn.querySelector('[data-count]');
        if (counter && count !== undefined) counter.textContent = count;
        const label = btn.querySelector('[data-label]');
        if (label) label.textContent = on ? btn.dataset.on : btn.dataset.off;
    });

    // change it straight away; the server only confirms
    const counter = button.querySelector('[data-count]');
    const before = counter ? parseInt(counter.textContent, 10) || 0 : undefined;
    paint(!wasOn, before === undefined ? undefined : Math.max(0, before + (wasOn ? -1 : 1)));
    if (kind === 'like' && !wasOn && !reduceMotion()) {
        button.classList.remove('heart-pop');
        void button.offsetWidth;
        button.classList.add('heart-pop');
    }
    if (kind === 'bookmark') toast(!wasOn ? t('saved_fav', 'Saved to favourites') : t('removed_fav', 'Removed from favourites'));

    // quick double taps: only the last answer counts
    const run = (Number(form.dataset.run) || 0) + 1;
    form.dataset.run = run;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        });
        if (!response.ok) throw new Error(response.status);
        const data = await response.json();
        if (Number(form.dataset.run) !== run) return;

        paint(data.active, data.count);
        if (kind === 'follow') {
            const followers = document.querySelector('[data-followers-count]');
            if (followers && data.count !== undefined) followers.textContent = data.count;
        }
        showBadges(data.badges);
    } catch (error) {
        if (Number(form.dataset.run) !== run) return;
        paint(wasOn, before);
        toast(t('save_failed', 'Could not save. Please try again.'));
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

// pick the most natural voice the device has for this language (no setting needed).
// Neural voices (Edge "Online (Natural)", Apple "Premium", Google) sound close to a real person;
// the old desktop voices sound robotic, so they are only used when nothing else exists.
const HUMAN_VOICES = /natural|neural|premium|enhanced|siri|hamed|zariyah|shakir|aria|jenny|guy|ava|andrew|emma|brian|samantha|daniel|majed|maged|tarik|laila|google/;
const ROBOTIC_VOICES = /compact|espeak|naayf|hoda|david|zira|mark|hazel|george/;
// joke voices that come with iPhones and Macs
const NOVELTY_VOICES = /albert|bad news|bahh|bells|boing|bubbles|cellos|fred|good news|jester|junior|kathy|organ|ralph|superstar|trinoids|whisper|wobble|zarvox|grandma|grandpa|eddy|flo|reed|rocko|sandy|shelley/;

function bestVoice(lang) {
    const base = lang.slice(0, 2).toLowerCase();
    const score = (voice) => {
        const name = voice.name.toLowerCase();
        let points = 0;
        if (HUMAN_VOICES.test(name)) points += 8;
        if (/online/.test(name)) points += 4;
        if (/google/.test(name)) points += 3;
        if (voice.lang.toLowerCase() === lang.toLowerCase()) points += 2; // same country (ar-SA, en-US)
        if (!voice.localService) points += 1;
        if (ROBOTIC_VOICES.test(name)) points -= 6;
        if (NOVELTY_VOICES.test(name)) points -= 20;
        return points;
    };
    const best = window.speechSynthesis.getVoices()
        .filter((v) => v.lang.toLowerCase().replace('_', '-').startsWith(base))
        .sort((a, b) => score(b) - score(a))[0];
    // on phones the system's own voice is usually the clearest, so only pick one when it is clearly human
    return best && HUMAN_VOICES.test(best.name.toLowerCase()) && !NOVELTY_VOICES.test(best.name.toLowerCase()) ? best : null;
}

// clean the text so it is read like a person would say it
function speakableText(text, lang) {
    const pause = lang.startsWith('ar') ? '، ' : ', ';
    return text
        .replace(/[«»“”"]/g, '')
        .replace(/\s+[—–-]\s+|[—–]/g, pause)
        .replace(/\s+/g, ' ')
        .trim();
}
// some browsers load their voices a moment after the page
window.speechSynthesis?.addEventListener?.('voiceschanged', () => {});

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

    const lang = button.dataset.lang || 'en-US';
    const voice = bestVoice(lang);
    // the quote first, then who said it after a short natural pause
    const parts = [button.dataset.speak, button.dataset.speakAuthor].filter(Boolean).map((part) => speakableText(part, lang));
    const utterances = parts.map((part) => {
        const utterance = new SpeechSynthesisUtterance(part);
        utterance.lang = lang;
        utterance.rate = parseFloat(root.dataset.speechRate) || 1;
        utterance.pitch = 1;
        if (voice) utterance.voice = voice;
        return utterance;
    });
    const last = utterances[utterances.length - 1];
    last.onend = () => {
        if (run === speechRun) stopSpeaking();
    };
    utterances.forEach((utterance) => {
        utterance.onerror = (event) => {
            if (run === speechRun && event.error !== 'interrupted') stopSpeaking();
        };
    });

    speakingButton = button;
    button.classList.add('is-speaking');
    button.setAttribute('aria-pressed', 'true');
    if (speechBar) {
        speechBar.querySelector('[data-speech-text]').textContent = button.dataset.speak;
        speechBar.hidden = false;
        root.dataset.speaking = '1';
        markRate();
    }
    utterances.forEach((utterance, index) => {
        if (index === 0) {
            window.speechSynthesis.speak(utterance);
        } else {
            utterances[index - 1].onend = () => run === speechRun && setTimeout(() => run === speechRun && window.speechSynthesis.speak(utterance), 350);
        }
    });
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

// ---------- bell, live numbers and chat ----------
function setBadge(name, value) {
    document.querySelectorAll(`[data-live="${name}"]`).forEach((badge) => {
        badge.textContent = value;
        badge.hidden = !value;
    });
}

// opening the bell marks the new quotes as seen
const bellMenu = document.getElementById('bell-menu');
bellMenu?.addEventListener('toggle', (event) => {
    if (event.newState !== 'open') return;
    fetch(bellMenu.dataset.seenUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, keepalive: true }).catch(() => {});
    setBadge('notifications', 0);
});

// numbers on the bell and Messages stay fresh without reloading
if (QI.liveUrl) {
    const refreshLive = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(QI.liveUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            if (!bellMenu?.matches(':popover-open')) setBadge('notifications', data.notifications);
            if (!document.querySelector('[data-chat]')) setBadge('messages', data.messages);
        } catch (e) {}
    };
    setInterval(refreshLive, 25000);
}

const chat = document.querySelector('[data-chat]');
const chatForm = document.querySelector('[data-chat-form]');
if (chat && chatForm) {
    const box = chatForm.querySelector('textarea');
    const scrollDown = () => (chat.scrollTop = chat.scrollHeight);
    const seen = new Set();

    const bubble = (message) => {
        chat.querySelector('[data-chat-empty]')?.remove();
        const el = document.createElement('div');
        el.className = 'bubble' + (message.mine ? ' bubble-mine' : '');
        el.dir = 'auto';
        const body = document.createElement('span');
        body.className = 'whitespace-pre-line';
        body.textContent = message.body;
        const time = document.createElement('span');
        time.className = 'bubble-time';
        time.textContent = message.time;
        el.append(body, time);
        chat.append(el);
        return el;
    };

    const poll = async () => {
        try {
            const response = await fetch(`${chat.dataset.pollUrl}?after=${chat.dataset.lastId}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const { messages } = await response.json();
            messages.forEach((message) => {
                chat.dataset.lastId = Math.max(Number(chat.dataset.lastId), message.id);
                if (seen.has(message.id)) return;
                seen.add(message.id);
                bubble(message);
            });
            if (messages.length) scrollDown();
        } catch (e) {}
    };

    chatForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const text = box.value.trim();
        if (!text) return;
        // show it at once, the server confirms
        const pending = bubble({ mine: true, body: text, time: '…' });
        box.value = '';
        scrollDown();
        try {
            const response = await fetch(chatForm.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ body: text }),
            });
            if (!response.ok) throw new Error(response.status);
            const { message } = await response.json();
            seen.add(message.id);
            chat.dataset.lastId = Math.max(Number(chat.dataset.lastId), message.id);
            pending.querySelector('.bubble-time').textContent = message.time;
        } catch (e) {
            pending.remove();
            box.value = text;
            toast(t('message_failed', 'The message was not sent. Check your connection and try again.'));
        }
    });

    // Enter sends, Shift + Enter makes a new line
    box.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            chatForm.requestSubmit();
        }
    });

    scrollDown();
    setInterval(() => !document.hidden && poll(), 4000);
}

// ---------- translate a quote (Arabic <-> English) inside the page ----------
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-translate]');
    if (!button) return;
    const box = button.closest('article')?.querySelector('[data-translation-box]');
    if (!box) return;

    // second press hides it again
    if (box.dataset.done) {
        box.classList.toggle('hidden');
        return;
    }
    // status lines follow the page language; the translation itself keeps its own direction
    box.dataset.dir ??= box.dir;
    box.dir = document.documentElement.dir || 'auto';
    box.classList.remove('hidden');
    box.textContent = t('translating', 'Translating…');
    button.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(button.dataset.translate, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message);
        box.dir = box.dataset.dir;
        box.textContent = data.text;
        const note = document.createElement('span');
        note.className = 'mt-1 block text-[11px] text-tuwaiq-lilac';
        note.textContent = data.note;
        box.append(note);
        box.dataset.done = '1';
    } catch (error) {
        box.textContent = error.message || t('translate_failed', 'Translation is not available right now. Please try again later.');
    } finally {
        button.removeAttribute('aria-busy');
    }
});

// ---------- live preview of the quote look in the share / edit form ----------
document.querySelectorAll('[data-style-picker]').forEach((picker) => {
    const preview = picker.querySelector('[data-style-preview]');
    const form = picker.closest('form');
    const text = form.querySelector('textarea[name="content"]');
    const update = () => {
        const value = (name, fallback) => form.querySelector(`[name="style[${name}]"]:checked`)?.value || fallback;
        preview.className = preview.className.replace(/\bqs-\S+/g, '').trim()
            + ` qs-font-${value('font', 'almarai')} qs-size-${value('size', 'm')} qs-color-${value('color', 'default')} qs-align-${value('align', 'start')}`;
        if (text?.value.trim()) preview.textContent = text.value.trim();
    };
    picker.addEventListener('change', update);
    text?.addEventListener('input', update);
    update();
});

// ---------- stories: auto-advance (10 or 15 s), hold to pause, tap sides to move ----------
const storyViewer = document.querySelector('[data-story-viewer]');
const storyData = document.querySelector('[data-story-data]');
if (storyViewer && storyData) {
    const groups = JSON.parse(storyData.textContent);
    const $ = (sel) => storyViewer.querySelector(sel);
    const isRtl = document.documentElement.dir === 'rtl';
    let gi = 0, si = 0, elapsed = 0, last = 0, paused = false, frame = 0, opener = null;

    const tick = (now) => {
        if (!paused) elapsed += now - last;
        last = now;
        const story = groups[gi].stories[si];
        const fill = $('[data-story-progress]').children[si]?.firstChild;
        const part = Math.min(1, elapsed / (story.seconds * 1000));
        if (fill) fill.style.width = `${part * 100}%`;
        if (part >= 1) return next();
        frame = requestAnimationFrame(tick);
    };

    const show = (index) => {
        cancelAnimationFrame(frame);
        si = index;
        const group = groups[gi];
        const story = group.stories[si];
        [...$('[data-story-progress]').children].forEach((track, i) => (track.firstChild.style.width = i < si ? '100%' : '0'));
        const text = $('[data-story-text]');
        text.className = `quote-text text-2xl text-[#fff] ${story.classes}`;
        text.dir = story.rtl ? 'rtl' : 'ltr';
        text.textContent = story.rtl ? `«${story.content}»` : `“${story.content}”`;
        $('[data-story-author]').textContent = story.author ? `— ${story.author}` : '';
        $('[data-story-ago]').textContent = story.ago;
        $('[data-story-left]').textContent = story.left;
        const like = $('[data-story-like]');
        if (like) like.setAttribute('aria-pressed', story.liked ? 'true' : 'false');
        $('[data-story-likes]').textContent = story.likes;
        const del = $('[data-story-delete]');
        del.hidden = !group.mine;
        del.action = story.deleteUrl;
        elapsed = 0;
        last = performance.now();
        frame = requestAnimationFrame(tick);
    };

    const open = (index, startAt = 0) => {
        gi = index;
        const group = groups[gi];
        $('[data-story-name]').textContent = group.name;
        $('[data-story-profile]').href = group.profile;
        const avatar = document.querySelector(`[data-story-open="${gi}"] .story-ring > *`);
        $('[data-story-avatar]').replaceChildren(avatar ? avatar.cloneNode(true) : '');
        $('[data-story-progress]').replaceChildren(...group.stories.map(() => {
            const track = document.createElement('span');
            track.className = 'story-bar-track';
            track.append(Object.assign(document.createElement('span'), { className: 'story-bar-fill' }));
            return track;
        }));
        storyViewer.hidden = false;
        document.body.style.overflow = 'hidden';
        setPaused(false);
        show(startAt);
    };

    const close = () => {
        cancelAnimationFrame(frame);
        storyViewer.hidden = true;
        document.body.style.overflow = '';
        opener?.focus();
    };

    function next() {
        if (si + 1 < groups[gi].stories.length) return show(si + 1);
        if (gi + 1 < groups.length) return open(gi + 1);
        close();
    }

    const prev = () => {
        if (si > 0) return show(si - 1);
        if (gi > 0) return open(gi - 1, groups[gi - 1].stories.length - 1);
        show(0);
    };

    const setPaused = (value) => {
        paused = value;
        $('[data-story-pause]').setAttribute('aria-label', value ? t('play', 'Play') : t('pause', 'Pause'));
        $('[data-story-pause]').style.opacity = value ? '0.6' : '1';
    };

    document.querySelectorAll('[data-story-open]').forEach((button) => button.addEventListener('click', () => {
        opener = button;
        open(Number(button.dataset.storyOpen));
        $('[data-story-close]').focus();
    }));
    $('[data-story-close]').addEventListener('click', close);
    // a long press pauses; it should not also move to the next story
    let held = false;
    $('[data-story-next]').addEventListener('click', () => (held ? (held = false) : next()));
    $('[data-story-prev]').addEventListener('click', () => (held ? (held = false) : prev()));
    $('[data-story-pause]').addEventListener('click', () => setPaused(!paused));

    // hold anywhere on the story to pause it
    const card = $('[data-story-card]');
    let holdTimer;
    card.addEventListener('pointerdown', (event) => {
        if (event.target.closest('button:not(.story-tap), a, form')) return;
        holdTimer = setTimeout(() => {
            held = true;
            setPaused(true);
        }, 220);
    });
    ['pointerup', 'pointerleave', 'pointercancel'].forEach((type) => card.addEventListener(type, () => {
        clearTimeout(holdTimer);
        if (paused && type !== 'pointerleave') setTimeout(() => setPaused(false), 0);
    }));

    storyViewer.addEventListener('click', (event) => event.target === storyViewer && close());
    document.addEventListener('keydown', (event) => {
        if (storyViewer.hidden) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowLeft') (isRtl ? next : prev)();
        if (event.key === 'ArrowRight') (isRtl ? prev : next)();
        if (event.key === ' ') {
            event.preventDefault();
            setPaused(!paused);
        }
    });

    $('[data-story-like]')?.addEventListener('click', async () => {
        const story = groups[gi].stories[si];
        story.liked = !story.liked;
        story.likes += story.liked ? 1 : -1;
        $('[data-story-like]').setAttribute('aria-pressed', story.liked ? 'true' : 'false');
        $('[data-story-likes]').textContent = story.likes;
        try {
            const response = await fetch(story.likeUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            if (!response.ok) throw new Error();
            const data = await response.json();
            Object.assign(story, { liked: data.active, likes: data.count });
            $('[data-story-likes]').textContent = data.count;
        } catch (e) {
            toast(t('save_failed', 'Could not save. Please try again.'));
        }
    });
}

// live preview in the "new story" window
document.querySelectorAll('[data-story-form]').forEach((form) => {
    const text = form.querySelector('textarea[name="content"]');
    const preview = form.querySelector('[data-story-preview-text]');
    const update = () => {
        const font = form.querySelector('[name="style[font]"]:checked')?.value || 'almarai';
        preview.className = `quote-text text-xl text-[#fff] qs-align-center qs-font-${font}`;
        if (text.value.trim()) preview.textContent = text.value.trim();
    };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
});

// ---------- first-visit tour: points at each main place, one step at a time ----------
(() => {
    const tour = QI.tour;
    if (!tour) return;
    let done = tour.done;
    try {
        done = done || localStorage.getItem('qi-tour-done') === '1';
    } catch (e) {}

    const visible = (el) => {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0 && getComputedStyle(el).visibility !== 'hidden';
    };

    const start = () => {
        const steps = tour.steps
            .map((step) => ({ ...step, el: step.target === 'intro' ? null : [...document.querySelectorAll(`[data-tour="${step.target}"]`)].find(visible) }))
            .filter((step) => step.target === 'intro' || step.el);
        if (!steps.length) return;

        const layer = document.createElement('div');
        layer.className = 'tour-layer';
        layer.innerHTML = `<div class="tour-spot"></div>
            <div class="tour-card" role="dialog" aria-modal="true" aria-live="polite">
                <p class="tour-count"></p><h2 class="tour-title"></h2><p class="tour-text"></p>
                <div class="tour-actions"><button type="button" class="tour-skip"></button>
                <span class="flex gap-2"><button type="button" class="btn-ghost px-3 py-1.5 text-sm tour-back"></button>
                <button type="button" class="btn-primary px-4 py-1.5 text-sm tour-next"></button></span></div></div>`;
        document.body.append(layer);
        const spot = layer.querySelector('.tour-spot');
        const card = layer.querySelector('.tour-card');
        layer.querySelector('.tour-skip').textContent = tour.skip;
        layer.querySelector('.tour-back').textContent = tour.back;
        let index = 0;

        const finish = () => {
            layer.remove();
            window.removeEventListener('resize', place);
            try {
                localStorage.setItem('qi-tour-done', '1');
            } catch (e) {}
            if (root.dataset.signedIn === '1') savePrefs({ tour_done: true });
        };

        function place() {
            const step = steps[index];
            if (!step.el) {
                spot.style.cssText = 'top:50%;left:50%;width:0;height:0';
                card.style.cssText = 'top:50%;left:50%;transform:translate(-50%,-50%)';
                return;
            }
            const r = step.el.getBoundingClientRect();
            const pad = 6;
            spot.style.cssText = `top:${r.top - pad}px;left:${r.left - pad}px;width:${r.width + pad * 2}px;height:${r.height + pad * 2}px`;
            const below = r.bottom + 16 + card.offsetHeight < innerHeight;
            const left = Math.min(Math.max(12, r.left + r.width / 2 - card.offsetWidth / 2), innerWidth - card.offsetWidth - 12);
            card.style.cssText = `left:${left}px;top:${below ? r.bottom + 14 : Math.max(12, r.top - card.offsetHeight - 14)}px`;
        }

        const show = () => {
            const step = steps[index];
            step.el?.scrollIntoView({ block: 'nearest', behavior: reduceMotion() ? 'auto' : 'smooth' });
            layer.querySelector('.tour-count').textContent = tour.count.replace(':step', index + 1).replace(':total', steps.length);
            layer.querySelector('.tour-title').textContent = step.title;
            layer.querySelector('.tour-text').textContent = step.text;
            layer.querySelector('.tour-back').hidden = index === 0;
            layer.querySelector('.tour-next').textContent = index === steps.length - 1 ? tour.done_label : tour.next;
            requestAnimationFrame(place);
            setTimeout(place, 350);
            layer.querySelector('.tour-next').focus();
        };

        layer.querySelector('.tour-next').addEventListener('click', () => (index === steps.length - 1 ? finish() : (index++, show())));
        layer.querySelector('.tour-back').addEventListener('click', () => index > 0 && (index--, show()));
        layer.querySelector('.tour-skip').addEventListener('click', finish);
        layer.addEventListener('keydown', (event) => event.key === 'Escape' && finish());
        window.addEventListener('resize', place);
        show();
    };

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-tour-start]')) return;
        document.querySelectorAll('[popover]:popover-open').forEach((p) => p.hidePopover());
        if (tour.onHome) start();
        else location.href = tour.homeUrl;
    });

    const asked = new URLSearchParams(location.search).has('tour');
    if (tour.onHome && (asked || !done)) setTimeout(start, 600);
})();

// ---------- new message: search people by name ----------
document.querySelectorAll('[data-people-search]').forEach((input) => {
    const results = input.closest('[popover]')?.querySelector('[data-people-results]');
    let timer = 0;
    let run = 0;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const mine = ++run;
            try {
                const url = `${input.dataset.peopleSearch}?q=${encodeURIComponent(input.value.trim())}`;
                const response = await fetch(url, { headers: { Accept: 'text/html' } });
                if (response.ok && mine === run) results.innerHTML = await response.text();
            } catch (error) {
                // keep the last list if the connection drops
            }
        }, 250);
    });
});
