// Uzdevumi — saskarnes uzvedība.
// Visas formas strādā arī bez JavaScript. Ar JavaScript tās tiek nosūtītas fonā (fetch),
// un lapā tiek nomainītas tikai tās daļas, kas mainījās ([data-region]) - bez pārlādes.
(() => {
    'use strict';

    const $  = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
    const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const fine = matchMedia('(pointer: fine)').matches;
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

    document.documentElement.classList.add('js');

    const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const store = {
        get(key) { try { return localStorage.getItem(key); } catch { return null; } },
        set(key, value) { try { localStorage.setItem(key, value); } catch { /* privātais režīms */ } },
    };

    // Šodienas datums vietējā laikā 'YYYY-MM-DD'
    const localDay = () => {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    };

    // Dienu skaits līdz datumam 'YYYY-MM-DD' (negatīvs = pagātnē)
    const daysUntil = (iso) => {
        if (!iso) return null;
        const [y, m, d] = iso.split('-').map(Number);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return Math.round((new Date(y, m - 1, d) - today) / 86400000);
    };

    const getRows = () => $$('[data-task]');

    /* ---------- Lapu pārejas: pārtraukta pāreja nav kļūda ---------- */
    const quietTransition = (e) => {
        const vt = e.viewTransition;
        if (vt) [vt.ready, vt.finished, vt.updateCallbackDone].forEach((p) => p?.catch(() => {}));
    };
    addEventListener('pagereveal', quietTransition);
    addEventListener('pageswap', quietTransition);

    /* ---------- Saule seko diennakts laikam ---------- */
    const placeSun = () => {
        const now = new Date();
        const hours = now.getHours() + now.getMinutes() / 60;
        const t = Math.min(Math.max((hours - 6) / 15, 0), 1);   // 06:00 → 0, 21:00 → 1
        $$('.hero .sun').forEach((sun) => {
            sun.style.setProperty('--sun-x', `${12 + t * 76}%`);
            sun.style.setProperty('--sun-y', `${78 - Math.sin(Math.PI * t) * 70}%`);
            sun.style.opacity = hours < 6 || hours > 21.5 ? '.35' : '';
        });
    };
    placeSun();
    setInterval(placeSun, 60000);

    /* ---------- Paziņojumi ---------- */
    const toastBox = $('.toasts');
    const hideToast = (t) => {
        t.classList.add('is-out');
        t.addEventListener('animationend', () => t.remove(), { once: true });
    };
    const showToast = ({ title, text = '', href, late, onClose, autohide }) => {
        if (!toastBox) return;
        const t = document.createElement('div');
        t.className = 'toast' + (late ? ' toast-late' : '');
        t.innerHTML = `<svg><use href="#i-${late ? 'x' : 'bell'}"/></svg>
            <p><b>${escapeHtml(title)}</b>${text ? `<small>${escapeHtml(text)}</small>` : ''}
            ${href ? `<br><a href="${escapeHtml(href)}">Atvērt</a>` : ''}</p>
            <button type="button" class="icon-btn" aria-label="Aizvērt"><svg><use href="#i-x"/></svg></button>`;
        $('button', t).addEventListener('click', () => { onClose?.(); hideToast(t); });
        toastBox.append(t);
        if (autohide) setTimeout(() => t.isConnected && hideToast(t), 4200);
    };
    const autohide = (root) => $$('.toast[data-autohide]', root).forEach((t) => setTimeout(() => t.isConnected && hideToast(t), 4200));
    autohide(document);
    // Servera paziņojumu (piem., demo kontu) aizvēršana
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-toast-close]');
        if (btn) hideToast(btn.closest('.toast'));
    });

    // Paziņojumi no fonā ielādētas lapas (flash) - pārnesam uz šo lapu
    const adoptToasts = (doc) => {
        $$('.toasts .toast', doc).forEach((t) => {
            const node = document.importNode(t, true);
            toastBox?.append(node);
            if (node.hasAttribute('data-autohide')) setTimeout(() => node.isConnected && hideToast(node), 4200);
        });
        // Validācijas kļūdas (.alert) parādām kā sarkanus paziņojumus
        $$('main .alert', doc).forEach((a) => showToast({ title: a.textContent.trim(), late: true, autohide: true }));
    };

    /* ---------- Skaņa: īsa melodija, ģenerēta ar Web Audio (bez failiem) ---------- */
    const sound = {
        ctx: null,
        get on() { return store.get('skana') !== 'off'; },
        play(big = false) {
            if (!this.on) return;
            try {
                this.ctx ||= new (window.AudioContext || window.webkitAudioContext)();
                const notes = big ? [523.25, 659.25, 783.99, 1046.5, 1318.5] : [659.25, 880, 1318.5];
                notes.forEach((freq, i) => {
                    const t = this.ctx.currentTime + i * 0.085;
                    const osc = this.ctx.createOscillator();
                    const gain = this.ctx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.value = freq;
                    gain.gain.setValueAtTime(0.0001, t);
                    gain.gain.exponentialRampToValueAtTime(0.18, t + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.45);
                    osc.connect(gain).connect(this.ctx.destination);
                    osc.start(t);
                    osc.stop(t + 0.5);
                });
            } catch { /* pārlūks neatbalsta - bez skaņas */ }
        },
    };
    /* ---------- Iestatījumi profilā: tēma un skaņa (glabājas tikai šajā pārlūkā) ---------- */
    const applyTheme = (value) => {
        if (value === 'light' || value === 'dark') {
            document.documentElement.dataset.theme = value;
        } else {
            delete document.documentElement.dataset.theme;
        }
    };
    $$('[data-pref]').forEach((group) => {
        const key = group.dataset.pref;                        // 'tema' vai 'skana'
        const current = store.get(key) || (key === 'tema' ? 'auto' : 'on');
        const input = $(`input[value="${current}"]`, group);
        if (input) input.checked = true;

        group.addEventListener('change', (e) => {
            store.set(key, e.target.value);
            if (key === 'tema') applyTheme(e.target.value);
            if (key === 'skana' && e.target.value === 'on') sound.play();
        });
    });

    /* ---------- Konfeti ---------- */
    const confetti = ({ x = innerWidth / 2, y = innerHeight / 2, count = 140, spread = 1 } = {}) => {
        if (calm) return;
        const canvas = document.createElement('canvas');
        canvas.className = 'confetti';
        const dpr = Math.min(devicePixelRatio || 1, 2);
        canvas.width = innerWidth * dpr;
        canvas.height = innerHeight * dpr;
        document.body.append(canvas);
        const g = canvas.getContext('2d');
        g.scale(dpr, dpr);

        const colors = ['#F2A541', '#E4572E', '#2F5D50', '#D6E2DE', '#FFD08A', '#7FC3AE'];
        const parts = Array.from({ length: count }, () => {
            const angle = -Math.PI / 2 + (Math.random() - .5) * Math.PI * spread;
            const speed = 6 + Math.random() * 9;
            return {
                x, y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                size: 5 + Math.random() * 6,
                rot: Math.random() * Math.PI,
                vr: (Math.random() - .5) * .35,
                color: colors[(Math.random() * colors.length) | 0],
                shape: Math.random() < .3 ? 'circle' : 'rect',
                life: 0,
            };
        });

        const tick = () => {
            g.clearRect(0, 0, innerWidth, innerHeight);
            let alive = 0;
            for (const p of parts) {
                p.life++;
                p.vy += 0.28;          // gravitācija
                p.vx *= 0.985;         // gaisa pretestība
                p.vy *= 0.985;
                p.x += p.vx;
                p.y += p.vy;
                p.rot += p.vr;
                if (p.y < innerHeight + 30 && p.life < 260) alive++;
                g.save();
                g.translate(p.x, p.y);
                g.rotate(p.rot);
                g.globalAlpha = Math.max(0, 1 - p.life / 260);
                g.fillStyle = p.color;
                if (p.shape === 'circle') {
                    g.beginPath();
                    g.arc(0, 0, p.size / 2, 0, Math.PI * 2);
                    g.fill();
                } else {
                    g.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2 * (1 + Math.sin(p.life / 6)));
                }
                g.restore();
            }
            alive ? requestAnimationFrame(tick) : canvas.remove();
        };
        requestAnimationFrame(tick);
    };
    // origin: elements vai {x, y}; big: visi uzdevumi pabeigti
    const celebrate = (origin, big = false) => {
        const r = origin?.getBoundingClientRect?.();
        const x = r ? r.left + r.width / 2 : origin?.x ?? innerWidth / 2;
        const y = r ? r.top + r.height / 2 : origin?.y ?? innerHeight / 2;
        confetti({ x, y, count: big ? 220 : 120 });
        if (big) {
            sound.play(true);
            setTimeout(() => confetti({ x: innerWidth * .15, y: innerHeight, count: 160, spread: .6 }), 250);
            setTimeout(() => confetti({ x: innerWidth * .85, y: innerHeight, count: 160, spread: .6 }), 400);
        }
    };
    const allDone = () => {
        const rows = getRows();
        return rows.length > 0 && rows.every((r) => r.dataset.status === 'pabeigts');
    };

    /* =========================================================
       Elementu iestatīšana. Izsauc lapas ielādē un pēc katras daļas nomaiņas.
       ========================================================= */
    const initRegion = (root, fresh = true) => {
        // Skaitļa "uzskaitīšana" (nomainītām daļām - uzreiz gala vērtība)
        $$('[data-count]', root).forEach((el) => {
            const target = Number(el.dataset.count);
            if (calm || !fresh) { el.textContent = target; return; }
            const start = performance.now() + 800;
            const tick = (now) => {
                const p = Math.min(Math.max((now - start) / 1600, 0), 1);
                el.textContent = Math.round(target * (1 - Math.pow(1 - p, 4)));
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        });

        $$('[data-task]', root).forEach((row, i) => row.style.setProperty('--n', Math.min(i, 12)));

        if (fine && !calm) {
            // Logrīku 3D noliekšana
            $$('[data-tilt]', root).forEach((el) => {
                el.addEventListener('pointermove', (e) => {
                    const r = el.getBoundingClientRect();
                    const x = (e.clientX - r.left) / r.width - .5;
                    const y = (e.clientY - r.top) / r.height - .5;
                    el.style.transform = `perspective(700px) rotateX(${-y * 10}deg) rotateY(${x * 12}deg) scale(1.02)`;
                });
                el.addEventListener('pointerleave', () => { el.style.transform = ''; });
            });
            // "Magnētiskās" pogas pievelkas kursoram
            $$('[data-magnetic]', root).forEach((el) => {
                el.addEventListener('pointermove', (e) => {
                    const r = el.getBoundingClientRect();
                    el.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * .25}px, ${(e.clientY - r.top - r.height / 2) * .35}px)`;
                });
                el.addEventListener('pointerleave', () => { el.style.transform = ''; });
            });
        }

        // Teksta lauki aug līdzi tekstam
        $$('textarea[data-autogrow]', root).forEach((ta) => {
            const grow = () => { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; };
            ta.addEventListener('input', grow);
            grow();
        });

        // Failu ievilkšana augšupielādes laukā
        $$('[data-dropzone]', root).forEach((form) => {
            const input = $('input[type="file"]', form);
            const label = $('[data-dropzone-label]', form);
            const show = () => { if (input.files[0]) label.textContent = input.files[0].name; };
            input.addEventListener('change', () => { show(); if (input.files[0]) form.requestSubmit(); });
            ['dragenter', 'dragover'].forEach((t) => form.addEventListener(t, (e) => { e.preventDefault(); form.classList.add('is-over'); }));
            ['dragleave', 'drop'].forEach((t) => form.addEventListener(t, (e) => { e.preventDefault(); form.classList.remove('is-over'); }));
            form.addEventListener('drop', (e) => {
                if (!e.dataTransfer.files.length) return;
                input.files = e.dataTransfer.files;
                show();
                form.requestSubmit();
            });
        });
    };
    initRegion(document);

    /* ---------- Gaismas punkts zem kursora uzdevumu sarakstā ---------- */
    if (fine) {
        document.addEventListener('pointermove', (e) => {
            const row = e.target.closest?.('.task');
            if (!row) return;
            const r = row.getBoundingClientRect();
            row.style.setProperty('--mx', `${e.clientX - r.left}px`);
            row.style.setProperty('--my', `${e.clientY - r.top}px`);
        });
    }

    /* ---------- Statusa filtrs ar slīdošu fonu ---------- */
    const segments = $('.segments');
    let currentFilter = null;   // null = filtrs netiek lietots pārlūkā
    const applyFilter = (filter, animate) => {
        let shown = 0;
        getRows().forEach((row) => {
            const match = !filter || row.dataset.status === filter;
            row.hidden = !match;
            if (match && animate) {
                row.style.animation = 'none';
                row.offsetHeight; // pārstartē animāciju
                row.style.animation = '';
                row.style.animationDelay = `${shown * 40}ms`;
            }
            if (match) shown++;
        });
        const noMatch = $('.no-match');
        if (noMatch) noMatch.hidden = shown > 0 || getRows().length === 0;
    };
    if (segments) {
        const movePill = () => {
            const on = $('a.is-on', segments);
            if (!on) return;
            segments.style.setProperty('--pill-x', `${on.offsetLeft}px`);
            segments.style.setProperty('--pill-w', `${on.offsetWidth}px`);
        };
        movePill();
        addEventListener('resize', movePill);
        document.fonts?.ready.then(movePill);

        // Ja lapā ir visi uzdevumi, filtrējam uzreiz pārlūkā (bez pārlādes)
        if (segments.hasAttribute('data-live')) {
            currentFilter = '';
            segments.addEventListener('click', (e) => {
                const link = e.target.closest('a[data-filter]');
                if (!link) return;
                e.preventDefault();
                $$('a', segments).forEach((a) => a.classList.toggle('is-on', a === link));
                movePill();
                currentFilter = link.dataset.filter;
                applyFilter(currentFilter, true);
                history.replaceState(null, '', link.href);
            });
        }
    }

    /* ---------- Dzēšana: 1. klikšķis jautā, 2. klikšķis dzēš ar animāciju ---------- */
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.del');
        if (!btn) return;
        e.preventDefault();

        if (!btn.classList.contains('is-armed')) {
            $$('.del.is-armed').forEach((b) => b.classList.remove('is-armed'));
            btn.classList.add('is-armed');
            clearTimeout(btn._disarm);
            btn._disarm = setTimeout(() => btn.classList.remove('is-armed'), 3000);
            return;
        }
        btn.closest('li')?.classList.add('is-leaving');
        btn.form.requestSubmit();
    });

    /* =========================================================
       Formu nosūtīšana bez pārlādes
       ========================================================= */

    // Vai adrese ir tā pati lapa (salīdzina ?r= un &id=)?
    const pageKey = (url) => {
        const u = new URL(url, location.href);
        return (u.searchParams.get('r') || 'tasks') + '|' + (u.searchParams.get('id') || '');
    };

    // Nomaina [data-region] daļas ar jaunajām no doc.
    // Daļas ar data-region-self (piem., redaktors) mainām tikai tad, ja forma nāca no tām,
    // lai nepazustu teksts, ko lietotājs šobrīd raksta.
    // Lauka atslēga daļas iekšienē: formas adrese + lauka vārds + kārtas nr. starp vienādiem
    // (piem., katrai kategorijai ir sava pārdēvēšanas forma ar vienādu adresi un lauku)
    const fieldKeys = (root) => {
        const seen = {};
        return $$('input[type="text"], input[type="url"], textarea', root)
            .filter((f) => f.form && f.name)
            .map((f) => {
                const base = f.form.getAttribute('action') + '|' + f.name;
                seen[base] = (seen[base] || 0) + 1;
                return [base + '|' + seen[base], f];
            });
    };

    const swapRegions = (doc, form) => {
        const active = document.activeElement;
        // Pogas un saites ar data-focus-id pēc nomaiņas atkal saņem fokusu (tastatūras lietotājiem)
        const focusId = active?.closest?.('[data-focus-id]')?.dataset.focusId;

        $$('[data-region]').forEach((oldEl) => {
            if (oldEl.hasAttribute('data-region-self') && !oldEl.contains(form)) return;
            const newEl = $(`[data-region="${oldEl.dataset.region}"]`, doc);
            if (!newEl) return;

            // Saglabājam tekstu, ko lietotājs ierakstīja, bet vēl nenosūtīja (piem., iesākts komentārs),
            // un atceramies, kurā laukā bija kursors
            const oldFields = fieldKeys(oldEl);
            const kept = oldFields.filter(([, f]) => f.form !== form && f.value !== f.defaultValue);
            const activeKey = oldFields.find(([, f]) => f === active)?.[0];

            const fresh = document.importNode(newEl, true);
            fresh.classList.add('quiet');
            oldEl.replaceWith(fresh);
            initRegion(fresh, false);

            const newFields = new Map(fieldKeys(fresh));
            kept.forEach(([key, f]) => { if (newFields.has(key)) newFields.get(key).value = f.value; });
            if (activeKey) newFields.get(activeKey)?.focus();
        });

        if (focusId && !document.activeElement?.matches('input, textarea')) {
            // Ja tieši tā poga vairs nav (piem., kartīte pārvietota uz malējo kolonnu) - fokusējam kartīti
            const target = $(`[data-focus-id="${focusId}"]`) || $(`[data-focus-id="${focusId.replace(/-(left|right)$/, '')}"]`);
            target?.focus();
        }
        if (currentFilter !== null) applyFilter(currentFilter, false);
        palette?.invalidate();
    };

    // Ielādē pašreizējo lapu fonā un nomaina mainītās daļas
    const refresh = async (form, origin) => {
        const html = await (await fetch(location.href, { credentials: 'same-origin' })).text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        swapRegions(doc, form);
        adoptToasts(doc);
        if (doc.body.hasAttribute('data-celebrate')) celebrate(origin, allDone());
    };

    // Kuras formas sūtām fonā: visas POST formas lapas saturā pieteikušamies lietotājiem
    const isAjaxForm = (form) =>
        document.body.classList.contains('app')
        && form.method.toLowerCase() === 'post'
        && form.closest('main.stage')
        && !form.hasAttribute('data-no-ajax');

    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!isAjaxForm(form)) return;
        e.preventDefault();
        if (form.classList.contains('is-busy')) return;

        // Uzreiz redzama reakcija (pirms serveris atbild)
        let wait = 0;
        let origin = form;
        const tick = $('.tick', form);
        if (form.matches('.tick-form')) {
            const row = form.closest('.task');
            tick.classList.add('is-bursting');
            row?.classList.toggle('status-done');
            if (row?.classList.contains('status-done')) sound.play();
            wait = 420;
        } else if (tick && form.getAttribute('action').includes('subtasks/toggle')) {
            const li = form.closest('li');
            tick.classList.add('is-bursting');
            li?.classList.toggle('is-done');
            wait = 250;
        } else if (form.closest('.is-leaving')) {
            wait = 450;
        }
        if (calm) wait = 0;
        const r = (tick || form).getBoundingClientRect();
        origin = { x: r.left + r.width / 2, y: r.top + r.height / 2 };

        form.classList.add('is-busy');
        try {
            const [res] = await Promise.all([
                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form, e.submitter),
                    credentials: 'same-origin',
                    headers: { 'X-Partial': '1' },
                }),
                sleep(wait),
            ]);
            const text = await res.text();
            // JSON atbilde var būt arī aiz PHP brīdinājuma teksta (piem., par lielu failu)
            const jsonPart = text.match(/\{"ok":(?:true|false)[\s\S]*\}\s*$/);

            if (jsonPart) {
                const data = JSON.parse(jsonPart[0]);
                if (!data.ok) throw new Error(data.error || 'Neizdevās saglabāt.');
                if (data.redirect && pageKey(data.redirect) !== pageKey(location.href)) {
                    location.href = data.redirect;   // cita lapa (piem., jauns uzdevums) - pārejam uz to
                    return;
                }
                await refresh(form, origin);
                if (document.contains(form) && !form.closest('[data-region-self]')) form.reset();
            } else if (res.ok) {
                // Serveris atgrieza lapu ar validācijas kļūdām
                const doc = new DOMParser().parseFromString(text, 'text/html');
                swapRegions(doc, form);
                adoptToasts(doc);
            } else {
                throw new Error(text.replace(/<[^>]+>/g, '').trim().slice(0, 200) || 'Neizdevās saglabāt.');
            }
        } catch (err) {
            showToast({ title: 'Neizdevās', text: err.message, late: true, autohide: true });
            // Atjaunojam lapu, lai atceltu uzreiz parādītās izmaiņas
            refresh(form, origin).catch(() => {});
        } finally {
            form.classList.remove('is-busy');
        }
    });

    // Serveris pēc pabeigšanas uzstāda data-celebrate (pilnas lapas ielādē)
    if (document.body.hasAttribute('data-celebrate')) {
        setTimeout(() => celebrate(null, allDone()), 350);
    }

    /* ---------- Pieprasījumi no dēļa un kalendāra (JSON) ---------- */
    const csrf = $('meta[name="csrf-token"]')?.content || '';
    const post = async (route, data) => {
        const body = new FormData();
        body.append('csrf_token', csrf);
        Object.entries(data).forEach(([k, v]) => body.append(k, v));
        const res = await fetch('index.php?r=' + route, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch' },
        });
        const json = await res.json().catch(() => ({ ok: false }));
        if (!res.ok || !json.ok) throw new Error(json.error || 'Neizdevās saglabāt.');
        return json;
    };

    /* ---------- Vilkšana ar peli vai pirkstu (dēlis un kalendārs) ---------- */
    // rootSelector: konteinera selektors (notikumi tiek klausīti uz document, tāpēc vilkšana
    // strādā arī pēc tam, kad konteiners nomainīts bez pārlādes);
    // onDrop(card, zone, from) atgriež Promise; reorder: vai kārtot kartītes zonas iekšienē
    const dragAndDrop = (rootSelector, { onDrop, reorder = false, zoneHost }) => {
        let drag = null;
        let suppressClick = false;

        document.addEventListener('pointerdown', (e) => {
            const root = e.target.closest?.(rootSelector);
            const card = root && e.target.closest('[data-card]');
            if (!card || e.button !== 0 || e.target.closest('button, form, input')) return;
            drag = { root, card, startX: e.clientX, startY: e.clientY, started: false, from: card.parentElement, next: card.nextElementSibling };
        });

        addEventListener('pointermove', (e) => {
            if (!drag) return;
            const { card, root } = drag;
            if (!drag.started) {
                if (Math.hypot(e.clientX - drag.startX, e.clientY - drag.startY) < 6) return;
                drag.started = true;
                const r = card.getBoundingClientRect();
                drag.dx = drag.startX - r.left;
                drag.dy = drag.startY - r.top;
                drag.ghost = card.cloneNode(true);
                drag.ghost.classList.add('drag-ghost');
                drag.ghost.style.width = r.width + 'px';
                document.body.append(drag.ghost);
                card.classList.add('is-dragging');
            }
            e.preventDefault();
            drag.ghost.style.left = e.clientX - drag.dx + 'px';
            drag.ghost.style.top = e.clientY - drag.dy + 'px';

            const under = document.elementFromPoint(e.clientX, e.clientY);
            const host = under?.closest(zoneHost);
            const zone = host && root.contains(host) ? $('[data-drop]', host) : null;
            $$(zoneHost + '.is-over', root).forEach((h) => h !== host && h.classList.remove('is-over'));
            host?.classList.add('is-over');
            drag.zone = zone;

            if (zone && reorder) {
                const after = [...zone.querySelectorAll('[data-card]:not(.is-dragging)')]
                    .find((c) => e.clientY < c.getBoundingClientRect().top + c.offsetHeight / 2);
                zone.insertBefore(card, after || null);
            }
        }, { passive: false });

        const end = async () => {
            if (!drag) return;
            const { root, card, started, ghost, zone, from, next } = drag;
            drag = null;
            if (!started) return;
            suppressClick = true;
            setTimeout(() => { suppressClick = false; }, 0);
            ghost.remove();
            card.classList.remove('is-dragging');
            $$('.is-over', root).forEach((h) => h.classList.remove('is-over'));

            if (!zone) { from.insertBefore(card, next); return; }
            if (!reorder) zone.append(card);
            card.classList.add('just-dropped');
            setTimeout(() => card.classList.remove('just-dropped'), 600);

            if (zone === from) return;
            try {
                await onDrop(card, zone, from);
            } catch (err) {
                from.insertBefore(card, next);   // atgriežam atpakaļ
                showToast({ title: 'Neizdevās', text: err.message, late: true, autohide: true });
            }
        };
        addEventListener('pointerup', end);
        addEventListener('pointercancel', end);

        // Pēc vilkšanas neatveram saiti
        document.addEventListener('click', (e) => {
            if (suppressClick && e.target.closest?.(rootSelector)) { e.preventDefault(); e.stopPropagation(); }
        }, true);
        document.addEventListener('dragstart', (e) => { if (e.target.closest?.(rootSelector)) e.preventDefault(); });
    };

    /* ---------- Kanban dēlis ---------- */
    if ($('[data-kanban]')) {
        const recount = () => $$('[data-kanban] .kb-col').forEach((col) => {
            $('.kb-count', col).textContent = $$('[data-card]', col).length;
        });
        dragAndDrop('[data-kanban]', {
            reorder: true,
            zoneHost: '.kb-col',
            onDrop: async (card, zone) => {
                recount();
                const status = zone.closest('.kb-col').dataset.status;
                const res = await post('tasks/status', { id: card.dataset.id, status });
                if (status === 'pabeigts') {
                    sound.play();
                    celebrate(card);
                }
                if (res.spawned) {
                    showToast({ title: 'Atkārtots uzdevums', text: 'Izveidots nākamais atkārtojums.', href: 'index.php?r=tasks/edit&id=' + res.spawned, autohide: true });
                }
                // Dēli ielādējam no jauna: kartītes bultiņas un jaunais atkārtojums atbilst serverim
                refresh(null, null).catch(() => {});
            },
        });
    }

    /* ---------- Kalendārs ---------- */
    if ($('[data-calendar]')) {
        dragAndDrop('[data-calendar]', {
            zoneHost: '.cal-day',
            onDrop: async (card, zone) => {
                const date = zone.closest('.cal-day').dataset.date;
                await post('tasks/due', { id: card.dataset.id, due_date: date });
            },
        });
    }
    /* ---------- Taustiņš N - jauns uzdevums ---------- */
    document.addEventListener('keydown', (e) => {
        if (e.key.toLowerCase() !== 'n' || e.ctrlKey || e.metaKey || e.altKey) return;
        const el = document.activeElement;
        if (/^(INPUT|TEXTAREA|SELECT)$/.test(el?.tagName) || el?.isContentEditable) return;
        if (!document.body.classList.contains('app') || $('.palette')?.hidden === false) return;
        location.href = 'index.php?r=tasks/create';
    });

    /* ---------- Komentārs: Ctrl+Enter nosūta ---------- */
    document.addEventListener('keydown', (e) => {
        const ta = e.target.closest?.('.comment-form textarea');
        if (ta && e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); ta.form.requestSubmit(); }
    });

    /* ---------- Termiņu atgādinājumi ---------- */
    const rowsAtLoad = getRows();
    if (rowsAtLoad.length && toastBox) {
        const dayKey = 'atgadinajumi-' + localDay();
        const dismissed = new Set(JSON.parse(store.get(dayKey) || '[]'));

        rowsAtLoad
            .filter((r) => r.dataset.status !== 'pabeigts' && r.dataset.due)
            .map((r) => ({ row: r, days: daysUntil(r.dataset.due) }))
            .filter((x) => x.days <= 1 && !dismissed.has(x.row.dataset.href))
            .sort((a, b) => a.days - b.days)
            .slice(0, 3)
            .forEach(({ row, days }, i) => {
                const { title, href } = row.dataset;
                const text = days < 0 ? `Termiņš nokavēts pirms ${-days} d.` : days === 0 ? 'Termiņš ir šodien' : 'Termiņš ir rīt';
                setTimeout(() => showToast({
                    title, text, href,
                    late: days < 0,
                    onClose: () => {
                        dismissed.add(href);
                        store.set(dayKey, JSON.stringify([...dismissed]));
                    },
                }), 1600 + i * 350);
            });
    }

    /* ---------- Meklēšanas logs (Ctrl+K vai /) ---------- */
    const palette = (() => {
        const box = $('.palette');
        if (!box) return null;
        const input = $('input', box);
        const list = $('.palette-results', box);
        const commands = [
            { title: 'Jauns uzdevums', href: 'index.php?r=tasks/create', hint: 'izveidot', icon: 'plus' },
            { title: 'Visi uzdevumi', href: 'index.php?r=tasks', hint: 'atvērt', icon: 'tasks' },
            { title: 'Dēlis', href: 'index.php?r=board', hint: 'atvērt', icon: 'board' },
            { title: 'Kalendārs', href: 'index.php?r=calendar', hint: 'atvērt', icon: 'cal' },
            { title: 'Kategorijas', href: 'index.php?r=categories', hint: 'atvērt', icon: 'tags' },
        ];
        let tasks = null;
        let active = 0;

        const readTasks = (root) => $$('[data-task]', root).map((r) => ({
            title: r.dataset.title,
            desc: r.dataset.desc,
            cat: r.dataset.cat,
            status: r.dataset.status,
            href: r.dataset.href,
        }));

        // Uzdevumus ņemam no lapas vai, ja to nav, ielādējam saraksta lapu fonā
        const loadTasks = async () => {
            if (tasks) return tasks;
            if (getRows().length && segments?.hasAttribute('data-live')) return (tasks = readTasks(document));
            try {
                const html = await (await fetch('index.php?r=tasks', { credentials: 'same-origin' })).text();
                tasks = readTasks(new DOMParser().parseFromString(html, 'text/html'));
            } catch { tasks = []; }
            return tasks;
        };

        const mark = (text, q) => {
            const safe = escapeHtml(text);
            if (!q) return safe;
            const i = text.toLowerCase().indexOf(q);
            if (i < 0) return safe;
            return escapeHtml(text.slice(0, i)) + '<mark>' + escapeHtml(text.slice(i, i + q.length)) + '</mark>' + escapeHtml(text.slice(i + q.length));
        };

        const links = () => $$('a', list);
        const highlight = () => links().forEach((a, i) => a.classList.toggle('is-active', i === active));

        const render = () => {
            const q = input.value.trim().toLowerCase();
            const found = (tasks || []).filter((t) => !q || [t.title, t.desc, t.cat].some((v) => v.toLowerCase().includes(q))).slice(0, 8);
            const cmds = commands.filter((c) => !q || c.title.toLowerCase().includes(q));
            let html = '';
            let n = 0;
            if (found.length) {
                html += '<li class="group">Uzdevumi</li>';
                html += found.map((t) => `<li><a href="${escapeHtml(t.href)}" style="--n:${n++}">
                    <svg><use href="#i-${t.status === 'pabeigts' ? 'check' : 'tasks'}"/></svg>
                    <span>${mark(t.title, q)}</span><small>${escapeHtml(t.cat || t.status)}</small></a></li>`).join('');
            }
            if (cmds.length) {
                html += '<li class="group">Darbības</li>';
                html += cmds.map((c) => `<li><a href="${c.href}" style="--n:${n++}">
                    <svg><use href="#i-${c.icon}"/></svg>
                    <span>${mark(c.title, q)}</span><small>${c.hint}</small></a></li>`).join('');
            }
            list.innerHTML = html || '<li class="palette-empty">Nekas netika atrasts.</li>';
            active = 0;
            highlight();
        };

        const open = async () => {
            box.hidden = false;
            input.value = '';
            input.focus();
            render();
            await loadTasks();
            render();
        };
        const close = () => { box.hidden = true; };

        $$('[data-open-palette]').forEach((b) => b.addEventListener('click', open));
        $$('[data-close-palette]', box).forEach((b) => b.addEventListener('click', close));
        input.addEventListener('input', render);

        document.addEventListener('keydown', (e) => {
            const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName);
            if ((e.key === 'k' && (e.ctrlKey || e.metaKey)) || (e.key === '/' && !typing)) {
                e.preventDefault();
                box.hidden ? open() : close();
                return;
            }
            if (box.hidden) return;
            const all = links();
            if (e.key === 'Escape') close();
            if (!all.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % all.length; highlight(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); active = (active - 1 + all.length) % all.length; highlight(); }
            if (e.key === 'Enter' && all[active]) { e.preventDefault(); location.href = all[active].href; }
        });

        // Pēc izmaiņām lapā uzdevumu saraksts jāielādē no jauna
        return { invalidate: () => { tasks = null; } };
    })();

    /* ---------- Paroles stipruma josla ---------- */
    $$('input[data-strength]').forEach((inp) => {
        const bar = inp.parentElement.querySelector('.strength');
        inp.addEventListener('input', () => {
            const v = inp.value;
            let s = 0;
            if (v.length >= 8) s++;
            if (v.length >= 12) s++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
            if (/\d/.test(v)) s++;
            if (/[^A-Za-z0-9]/.test(v)) s++;
            const colors = ['#E4572E', '#E4572E', '#F2A541', '#F2A541', '#2F5D50', '#2F5D50'];
            bar.style.setProperty('--s', `${(s / 5) * 100}%`);
            bar.style.setProperty('--c', colors[s]);
        });
    });
})();
