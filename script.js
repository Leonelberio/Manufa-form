/**
 * Formulaire Devis Live — multi-étapes, LocalStorage, envoi CRM
 * L’URL d’envoi se configure via data-endpoint sur .gmq-wrap (ex: webhook CRM).
 */
(function () {
    "use strict";

    var wrap = document.querySelector(".gmq-wrap");
    if (!wrap) return;

    var ep = wrap.getAttribute("data-endpoint");
    var endpoint = ep && ep.trim() ? ep.trim() : "";

    var form = document.getElementById("gmqForm");
    var steps = Array.prototype.slice.call(document.querySelectorAll(".gmq-step"));
    var btnNext = document.getElementById("gmqNext");
    var btnBack = document.getElementById("gmqBack");
    var btnSubmit = document.getElementById("gmqSubmit");
    var btnReset = document.getElementById("gmqReset");
    var bar = document.getElementById("gmqBar");
    var stepText = document.getElementById("gmqStepText");
    var summary = document.getElementById("gmqSummary");
    var toast = document.getElementById("gmqToast");
    var successModal = document.getElementById("gmqSuccessModal");
    var successModalClose = document.getElementById("gmqSuccessModalClose");

    var LS_KEY = "gm_devis_live_v1";
    var current = 1;
    var total = steps.length;

    function showToast(msg) {
        toast.textContent = msg;
        toast.style.display = "block";
        window.setTimeout(function () {
            toast.style.display = "none";
        }, 3500);
    }

    function setStep(n) {
        current = Math.min(Math.max(n, 1), total);
        steps.forEach(function (s) {
            s.classList.remove("is-active");
        });
        var active = steps.find(function (s) {
            return parseInt(s.dataset.step, 10) === current;
        });
        if (active) active.classList.add("is-active");

        btnBack.disabled = current === 1;
        btnNext.style.display = current === total ? "none" : "inline-block";
        btnSubmit.style.display = current === total ? "inline-block" : "none";

        var pct = Math.round(((current - 1) / (total - 1)) * 100);
        bar.style.width = pct + "%";
        stepText.textContent = "Étape " + current + "/" + total;

        if (current === total) updateSummary();
    }

    function getFormDataObject() {
        var hpEl = document.getElementById("gmqHp");
        var obj = {
            general: {},
            event: {},
            tech: {},
            logistics: {},
            post: {},
            contact: {},
            _hp: hpEl ? hpEl.value : ""
        };

        var inputs = form.querySelectorAll("input, select, textarea");
        for (var i = 0; i < inputs.length; i++) {
            var el = inputs[i];
            if (!el.name) continue;
            var name = el.name;
            if (name === "_hp") continue;

            var nameBase = name.replace(/\[\]$/, "");
            var parts = nameBase.split(".");
            if (parts.length !== 2) continue;

            var group = parts[0];
            var field = parts[1];

            if (el.type === "checkbox") {
                if (!obj[group][field]) obj[group][field] = [];
                if (el.checked) obj[group][field].push(el.value);
                continue;
            }
            if (el.type === "radio") {
                if (el.checked) obj[group][field] = el.value;
                continue;
            }
            obj[group][field] = el.value;
        }

        obj.event.platforms = obj.event.platforms || [];
        obj.tech.mics_needed = obj.tech.mics_needed || [];
        obj.tech.extra_content = obj.tech.extra_content || [];

        return obj;
    }

    function saveToLocal() {
        try {
            localStorage.setItem(LS_KEY, JSON.stringify(getFormDataObject()));
        } catch (e) {}
    }

    function clearLocal() {
        try {
            localStorage.removeItem(LS_KEY);
        } catch (e) {}
    }

    function loadFromLocal() {
        try {
            var raw = localStorage.getItem(LS_KEY);
            if (!raw) return;
            var obj = JSON.parse(raw);
            if (!obj || typeof obj !== "object") return;

            var groups = Object.keys(obj);
            for (var g = 0; g < groups.length; g++) {
                var group = groups[g];
                if (!obj[group] || typeof obj[group] !== "object") continue;
                var fields = Object.keys(obj[group]);
                for (var f = 0; f < fields.length; f++) {
                    var field = fields[f];
                    var value = obj[group][field];
                    var nameBase = group + "." + field;

                    if (Array.isArray(value)) {
                        value.forEach(function (v) {
                            var esc = String(v).replace(/"/g, "&quot;");
                            var cb = form.querySelector('input[name="' + nameBase + '[]"][value="' + esc + '"]');
                            if (cb) cb.checked = true;
                        });
                        continue;
                    }

                    var r = form.querySelector('input[type="radio"][name="' + nameBase + '"][value="' + String(value).replace(/"/g, "&quot;") + '"]');
                    if (r) {
                        r.checked = true;
                        continue;
                    }

                    var inputEl = form.querySelector("[name=\"" + nameBase + "\"]");
                    if (inputEl) inputEl.value = value;
                }
            }
        } catch (e) {}
    }

    function validateStep(stepNumber) {
        var step = steps.find(function (s) {
            return parseInt(s.dataset.step, 10) === stepNumber;
        });
        if (!step) return true;

        var ok = true;
        var required = step.querySelectorAll("[required]");
        for (var i = 0; i < required.length; i++) {
            var el = required[i];
            var v = (el.value || "").trim();
            if (!v) {
                ok = false;
                el.style.borderColor = "rgba(255,80,80,0.8)";
                (function (elem) {
                    window.setTimeout(function () {
                        elem.style.borderColor = "";
                    }, 1200);
                })(el);
            }
        }
        if (!ok) showToast("Veuillez remplir les champs requis (*) avant de continuer.");
        return ok;
    }

    var sectionLabels = {
        general: "Général",
        event: "Événement",
        tech: "Technique",
        logistics: "Logistique",
        post: "Post-production",
        contact: "Contact"
    };

    function fieldLabel(key) {
        var labels = {
            name_company: "Nom / Entreprise",
            contact_number: "Numéro ou contact",
            event_date: "Date de l'événement",
            location: "Lieu",
            duration: "Durée",
            event_type: "Type d'événement",
            broadcast_type: "Diffusion",
            platforms: "Plateformes",
            simulcast: "Simulcast",
            interaction: "Interaction",
            interaction_note: "Détails interaction",
            camera_count: "Nombre de caméras",
            capture_type: "Type de captation",
            audio_source: "Gestion du son",
            mics_needed: "Micros nécessaires",
            extra_content: "Contenu additionnel",
            internet: "Connexion Internet",
            bonded: "Kit 4G/5G / Starlink",
            scouting: "Repérage technique",
            recording: "Enregistrement",
            graphics: "Habillage graphique",
            translation: "Traduction",
            email: "Email",
            phone: "Téléphone",
            notes: "Notes / contraintes"
        };
        return labels[key] || key.replace(/_/g, " ");
    }

    function formatValue(val) {
        if (val === undefined || val === null) return "—";
        if (Array.isArray(val)) return val.length ? val.join(", ") : "—";
        if (typeof val === "string" && val.trim() === "") return "—";
        return String(val);
    }

    function updateSummary() {
        if (!summary) return;
        var obj = getFormDataObject();
        var html = "<table class=\"gmq-summary-table\"><thead><tr><th>Section</th><th>Champ</th><th>Valeur</th></tr></thead><tbody>";
        ["general", "event", "tech", "logistics", "post", "contact"].forEach(function (section) {
            var data = obj[section];
            if (!data || typeof data !== "object") return;
            var sectionName = sectionLabels[section] || section;
            Object.keys(data).forEach(function (key) {
                if (section === "contact" && key === "phone") return;
                var val = data[key];
                html += "<tr><td>" + sectionName + "</td><td>" + fieldLabel(key) + "</td><td>" + formatValue(val) + "</td></tr>";
            });
        });
        html += "</tbody></table>";
        summary.innerHTML = html;
    }

    form.addEventListener("input", saveToLocal);
    form.addEventListener("change", saveToLocal);

    btnNext.addEventListener("click", function () {
        if (!validateStep(current)) return;
        setStep(current + 1);
        saveToLocal();
    });

    btnBack.addEventListener("click", function () {
        setStep(current - 1);
        saveToLocal();
    });

    btnReset.addEventListener("click", function () {
        form.reset();
        clearLocal();
        setStep(1);
        showToast("Formulaire réinitialisé.");
    });

    function validatePayload(payload) {
        var g = payload.general || {};
        var c = payload.contact || {};
        if (!(g.name_company && (g.name_company = g.name_company.trim()).length)) {
            return "Veuillez renseigner le Nom / Entreprise.";
        }
        if (g.name_company.length > 200) {
            return "Nom / Entreprise : 200 caractères maximum.";
        }
        if (!(g.contact_number && (g.contact_number = g.contact_number.trim()).length)) {
            return "Veuillez renseigner le Numéro ou contact.";
        }
        if (g.contact_number.length > 50) {
            return "Numéro ou contact : 50 caractères maximum.";
        }
        if (!(g.event_date && (g.event_date = g.event_date.trim()).length)) {
            return "Veuillez renseigner la Date de l'événement.";
        }
        if (!/^\d{4}-\d{2}-\d{2}$/.test(g.event_date)) {
            return "Date de l'événement invalide (format attendu : AAAA-MM-JJ).";
        }
        var year = parseInt(g.event_date.slice(0, 4), 10);
        if (year < 2000 || year > 2100 || String(year).length !== 4) {
            return "Date de l'événement invalide : l'année doit être entre 2000 et 2100 (4 chiffres).";
        }
        if (!(g.location && (g.location = g.location.trim()).length)) {
            return "Veuillez renseigner le Lieu.";
        }
        if (g.location.length > 500) {
            return "Lieu : 500 caractères maximum.";
        }
        if (g.duration && g.duration.length > 200) {
            return "Durée : 200 caractères maximum.";
        }
        var email = (c.email && c.email.trim()) || "";
        if (email && email.length > 254) {
            return "E-mail : 254 caractères maximum.";
        }
        if (email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!re.test(email)) {
                return "Adresse e-mail invalide.";
            }
        }
        if (payload._hp) {
            return "Soumission refusée.";
        }
        return null;
    }

    form.addEventListener("submit", function (e) {
        e.preventDefault();
        if (!validateStep(current)) return;

        var payload = getFormDataObject();
        var err = validatePayload(payload);
        if (err) {
            showToast(err);
            return;
        }

        if (!endpoint) {
            showToast("Configurez l’URL d’envoi (attribut data-endpoint sur .gmq-wrap), par ex. votre webhook CRM.");
            return;
        }

        payload.meta = {
            source: "html_js_devis_live",
            submitted: new Date().toISOString()
        };

        btnSubmit.disabled = true;
        btnSubmit.textContent = "Envoi...";

        var pageParams = new URLSearchParams(window.location.search);
        var existingTypeSlug = (pageParams.get("event_type") || pageParams.get("type") || "").trim().toLowerCase();
        var eventTypeSlug = eventTypeToSlug((payload.event && payload.event.event_type) || "") || existingTypeSlug;
        var endpointWithType = buildEndpointWithEventType(endpoint, eventTypeSlug);

        fetch(endpointWithType, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        })
            .then(function (res) {
                return res.json().then(function (json) {
                    return { res: res, json: json };
                }).catch(function () {
                    return { res: res, json: {} };
                });
            })
            .then(function (o) {
                if (!o.res.ok || (o.json && o.json.ok === false)) {
                    throw new Error((o.json && o.json.message) || "Erreur lors de l’envoi.");
                }
                clearLocal();
                form.reset();
                setStep(1);
                if (successModal) {
                    successModal.removeAttribute("hidden");
                    successModal.setAttribute("aria-hidden", "false");
                } else {
                    showToast("Demande envoyée ✅ Vous serez recontacté.");
                }
            })
            .catch(function (err) {
                showToast(err.message || "Erreur réseau. Réessayez.");
            })
            .then(function () {
                btnSubmit.disabled = false;
                btnSubmit.textContent = "Envoyer";
            });
    });

    // Type d'événement : presélection depuis URL ?type= et mise à jour de l'URL au changement
    var typeParamMap = {
        reunions: "Réunions et conférence",
        "production-evenementielle": "Production évènementielle",
        "regie-live": "Régie audiovisuelle et live streaming"
    };
    var valueToSlug = {
        "Réunions et conférence": "reunions",
        "Production évènementielle": "production-evenementielle",
        "Régie audiovisuelle et live streaming": "regie-live"
    };
    function slugifyType(val) {
        if (!val || typeof val !== "string") return "";
        return val.trim().toLowerCase()
            .replace(/[àâä]/g, "a").replace(/[éèêë]/g, "e").replace(/[îï]/g, "i").replace(/[ôö]/g, "o").replace(/[ùûü]/g, "u").replace(/ç/g, "c")
            .replace(/\s+/g, "-").replace(/[^a-z0-9-]/g, "");
    }
    function eventTypeToSlug(value) {
        if (valueToSlug[value]) return valueToSlug[value];
        var s = slugifyType(value);
        return s || "";
    }
    function buildEndpointWithEventType(baseEndpoint, slug) {
        try {
            var u = new URL(baseEndpoint, window.location.href);
            if (slug) {
                u.searchParams.set("event_type", slug);
            } else {
                u.searchParams.delete("event_type");
            }
            return u.toString();
        } catch (e) {
            if (!slug) return baseEndpoint;
            var sep = baseEndpoint.indexOf("?") === -1 ? "?" : "&";
            return baseEndpoint + sep + "event_type=" + encodeURIComponent(slug);
        }
    }
    function applyTypeFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var typeSlug = (params.get("event_type") || params.get("type") || "").trim().toLowerCase();
        if (!typeSlug) return;
        var value = typeParamMap[typeSlug];
        var selectEl = form.querySelector("[name=\"event.event_type\"]");
        if (!selectEl) return;
        if (value && [].some.call(selectEl.options, function (o) { return o.value === value; })) {
            selectEl.value = value;
            saveToLocal();
            return;
        }
        for (var i = 0; i < selectEl.options.length; i++) {
            var opt = selectEl.options[i];
            if (opt.value && slugifyType(opt.value) === typeSlug) {
                selectEl.value = opt.value;
                saveToLocal();
                break;
            }
        }
    }
    function updateUrlType(slug) {
        var params = new URLSearchParams(window.location.search);
        if (slug) {
            params.set("event_type", slug);
            params.delete("type");
        } else {
            params.delete("event_type");
            params.delete("type");
        }
        var path = window.location.pathname === "/" ? "" : window.location.pathname;
        var query = params.toString();
        var nextUrl = window.location.origin + path + (query ? "?" + query : "");
        window.history.replaceState({}, "", nextUrl);
    }
    (function () {
        var selectEl = form.querySelector("[name=\"event.event_type\"]");
        if (selectEl) {
            selectEl.addEventListener("change", function () {
                var slug = eventTypeToSlug(selectEl.value);
                updateUrlType(slug);
            });
        }
    })();

    function closeSuccessModal() {
        if (!successModal) return;
        successModal.setAttribute("hidden", "");
        successModal.setAttribute("aria-hidden", "true");
    }
    if (successModalClose) {
        successModalClose.addEventListener("click", closeSuccessModal);
    }
    if (successModal) {
        var backdrop = successModal.querySelector(".gmq-modal-backdrop");
        if (backdrop) backdrop.addEventListener("click", closeSuccessModal);
        successModal.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && successModal.getAttribute("aria-hidden") === "false") {
                closeSuccessModal();
            }
        });
    }

    loadFromLocal();
    applyTypeFromUrl();
    setStep(1);
    saveToLocal();
})();
