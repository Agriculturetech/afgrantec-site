import rules from "../../api/rules.json";

/**
 * Validación y envío del formulario de contacto.
 *
 * - Valida cada campo al salir de él (focusout) y vuelve a validar en cada
 *   cambio una vez que el campo ya mostró un error.
 * - Al enviar, pinta el resumen, mueve el foco al primer campo inválido y envía
 *   el formulario por fetch. Sin JavaScript, el formulario hace POST normal.
 * - Las reglas y los textos vienen de api/rules.json (misma fuente que el PHP).
 */

type FieldName = "nombre" | "empresa" | "email" | "pais" | "telefono" | "produccion" | "mensaje" | "consent";

type FieldElement = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

const ORDER: FieldName[] = [
    "nombre",
    "empresa",
    "email",
    "pais",
    "telefono",
    "produccion",
    "mensaje",
    "consent",
];

const messages = rules.messages;
const limits = rules.limits;
const OTHER_COUNTRY = "otro";
const FALLBACK_EMAIL = "sebastian.lora@afgrantec.com";

const form = document.querySelector<HTMLFormElement>("#contact-form");

if (form) {
    // Referencia no nula para conservar el estrechamiento dentro de callbacks.
    const root: HTMLFormElement = form;

    const $ = <T extends Element>(selector: string): T => {
        const element = root.querySelector<T>(selector);
        if (!element) {
            throw new Error(`Falta el elemento ${selector}`);
        }
        return element;
    };

    const fields: Record<FieldName, FieldElement> = {
        nombre: $<HTMLInputElement>("#nombre"),
        empresa: $<HTMLInputElement>("#empresa"),
        email: $<HTMLInputElement>("#email"),
        pais: $<HTMLSelectElement>("#pais"),
        telefono: $<HTMLInputElement>("#telefono"),
        produccion: $<HTMLSelectElement>("#produccion"),
        mensaje: $<HTMLTextAreaElement>("#mensaje"),
        consent: $<HTMLInputElement>("#consent"),
    };

    const submitButton = $<HTMLButtonElement>("#contact-submit");
    const submitLabel = $<HTMLElement>("#submit-label");
    const status = $<HTMLElement>("#form-status");
    const summary = $<HTMLElement>("#form-summary");
    const fallback = $<HTMLAnchorElement>("#email-fallback");
    const context = $<HTMLElement>("#inquiry-context");
    const renderedAt = $<HTMLInputElement>("#form-rendered-at");
    const countryName = $<HTMLInputElement>("#pais-nombre");
    const origin = $<HTMLInputElement>("#origen");
    const topic = root.querySelector<HTMLTextAreaElement>("#mensaje");

    const touched = new Set<FieldName>();
    const defaultPhonePlaceholder = fields.telefono.getAttribute("placeholder") ?? "";
    const otherPhonePlaceholder = "Incluye el código de país, ej. +1 305 555 0100";
    let submitting = false;

    // Con JavaScript usamos nuestra validación; sin JavaScript, la del navegador.
    root.noValidate = true;

    const errorElement = (name: FieldName): HTMLElement | null =>
        document.getElementById(`error-${name}`);

    function validate(name: FieldName): string | null {
        const field = fields[name];
        const value = field instanceof HTMLInputElement && field.type === "checkbox"
            ? (field.checked ? "1" : "")
            : field.value;
        const trimmed = value.trim();

        switch (name) {
            case "nombre": {
                if (trimmed.length < limits.nombre.min) {
                    return messages.nombre;
                }
                return null;
            }
            case "empresa": {
                return trimmed.length > limits.empresa.max ? messages.empresa : null;
            }
            case "email": {
                if (trimmed === "") {
                    return messages.email_required;
                }
                const valid = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(trimmed);
                return valid ? null : messages.email_invalid;
            }
            case "pais": {
                return trimmed === "" ? messages.pais : null;
            }
            case "telefono": {
                const digits = trimmed.replace(/\D/g, "");
                if (digits === "") {
                    return messages.telefono_required;
                }
                if (digits.length < limits.telefono.minDigits || digits.length > limits.telefono.maxDigits) {
                    return messages.telefono_invalid;
                }
                return null;
            }
            case "produccion": {
                const allowed: string[] = limits.produccion.allowed;
                return allowed.includes(trimmed) ? null : messages.produccion;
            }
            case "mensaje": {
                if (trimmed === "") {
                    return messages.mensaje_required;
                }
                if (trimmed.length < limits.mensaje.min) {
                    return messages.mensaje_short;
                }
                return null;
            }
            case "consent": {
                return value === "1" ? null : messages.consent;
            }
            default:
                return null;
        }
    }

    function setError(name: FieldName, message: string | null): void {
        const error = errorElement(name);
        const field = fields[name];

        if (message) {
            touched.add(name);
            if (error) {
                error.textContent = message;
                error.hidden = false;
            }
            field.setAttribute("aria-invalid", "true");
            return;
        }

        if (error) {
            error.hidden = true;
            error.textContent = "";
        }
        field.removeAttribute("aria-invalid");
    }

    function validateAndPaint(name: FieldName): boolean {
        const message = validate(name);
        setError(name, message);

        return message === null;
    }

    function clearStatus(): void {
        status.hidden = true;
        status.textContent = "";
        status.removeAttribute("data-state");
        fallback.hidden = true;
        fallback.removeAttribute("href");
    }

    function showStatus(state: "ok" | "error", text: string): void {
        status.hidden = false;
        status.dataset.state = state;
        status.textContent = text;
    }

    function showFallback(text: string): void {
        showStatus("error", text);
        fallback.href = buildMailto();
        fallback.hidden = false;
    }

    function buildMailto(): string {
        const data = new FormData(root);
        const subject = `Consulta AFGRANTEC | ${String(data.get("produccion") ?? "")}`.trim();
        const body = [
            "Hola, equipo AFGRANTEC:",
            "",
            String(data.get("mensaje") ?? ""),
            "",
            `Nombre: ${String(data.get("nombre") ?? "")}`,
            `Empresa: ${String(data.get("empresa") ?? "") || "No indicada"}`,
            `Correo: ${String(data.get("email") ?? "")}`,
            `País o territorio: ${countryName.value || "No indicado"}`,
            `Teléfono: ${fields.telefono.value.trim()}`,
            `Producción: ${String(data.get("produccion") ?? "")}`,
        ].join("\n");

        return `mailto:${FALLBACK_EMAIL}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    }

    function syncCountry(): void {
        const select = fields.pais;
        if (!(select instanceof HTMLSelectElement)) {
            return;
        }

        const option = select.selectedOptions[0];
        const dial = select.value;

        countryName.value = dial === "" ? "" : String(option?.dataset.country ?? "");
        fields.telefono.setAttribute(
            "placeholder",
            dial === OTHER_COUNTRY ? otherPhonePlaceholder : defaultPhonePlaceholder,
        );
    }

    function applyServerErrors(errors: Record<string, string>): void {
        let firstInvalid: FieldElement | null = null;

        for (const name of ORDER) {
            if (Object.prototype.hasOwnProperty.call(errors, name)) {
                const message = errors[name];
                if (typeof message === "string" && message !== "") {
                    setError(name, message);
                    firstInvalid ??= fields[name];
                }
            }
        }

        summary.textContent = "Revisa los datos marcados antes de enviar.";
        summary.hidden = false;

        if (firstInvalid) {
            focusField(firstInvalid);
        }
    }

    function focusField(field: FieldElement): void {
        field.focus({ preventScroll: true });
        field.scrollIntoView({ block: "center", behavior: "smooth" });
    }

    function validateAll(): FieldElement | null {
        let firstInvalid: FieldElement | null = null;

        for (const name of ORDER) {
            const valid = validateAndPaint(name);
            if (!valid) {
                firstInvalid ??= fields[name];
            }
        }

        return firstInvalid;
    }

    function resetSubmitState(): void {
        submitting = false;
        submitButton.disabled = false;
        submitButton.removeAttribute("aria-busy");
        submitLabel.textContent = "Enviar";
    }

    async function send(event: SubmitEvent): Promise<void> {
        event.preventDefault();

        if (submitting) {
            return;
        }

        clearStatus();
        summary.hidden = true;
        summary.textContent = "";

        const firstInvalid = validateAll();
        if (firstInvalid) {
            const count = ORDER.filter((name) => fields[name].getAttribute("aria-invalid") === "true").length;
            summary.textContent = count === 1
                ? "Revisa el campo marcado antes de enviar."
                : `Revisa los ${count} campos marcados antes de enviar.`;
            summary.hidden = false;
            focusField(firstInvalid);
            return;
        }

        submitting = true;
        submitButton.disabled = true;
        submitButton.setAttribute("aria-busy", "true");
        submitLabel.textContent = "Enviando…";

        try {
            const response = await fetch(root.action, {
                method: "POST",
                body: new FormData(root),
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "fetch",
                },
                credentials: "same-origin",
            });

            if (response.ok) {
                const payload = await response.json().catch(() => null);

                if (payload && payload.ok === false && payload.errors) {
                    applyServerErrors(payload.errors as Record<string, string>);
                    return;
                }

                const originValue = origin.value;
                root.reset();
                touched.clear();
                ORDER.forEach((name) => setError(name, null));
                context.hidden = true;
                origin.value = originValue;
                syncCountry();
                renderedAt.value = String(Date.now());
                showStatus("ok", "Recibimos tu consulta. Te responderemos al correo que indicaste.");
                return;
            }

            if (response.status === 422) {
                const payload = await response.json().catch(() => null);
                if (payload && payload.errors) {
                    applyServerErrors(payload.errors as Record<string, string>);
                } else {
                    showStatus("error", "Revisa los datos y vuelve a intentarlo.");
                }
                return;
            }

            if (response.status === 429) {
                showStatus("error", "Has enviado varias consultas seguidas. Espera unos minutos y vuelve a intentarlo.");
                return;
            }

            if (response.status === 403 || response.status === 413) {
                showStatus("error", "No pudimos procesar el envío. Recarga la página e inténtalo de nuevo.");
                return;
            }

            showFallback("No pudimos completar el envío. Inténtalo de nuevo o escríbenos directamente por correo.");
        } catch {
            showFallback("No pudimos conectar con el servidor. Revisa tu conexión o escríbenos directamente por correo.");
        } finally {
            resetSubmitState();
        }
    }

    // --- Validación al salir del campo y revalidación tras el primer error ---
    for (const name of ORDER) {
        const field = fields[name];

        field.addEventListener("focusout", () => {
            validateAndPaint(name);
        });

        const liveEvent = field instanceof HTMLSelectElement || (field instanceof HTMLInputElement && field.type === "checkbox")
            ? "change"
            : "input";

        field.addEventListener(liveEvent, () => {
            if (touched.has(name) || field.getAttribute("aria-invalid") === "true") {
                validateAndPaint(name);
            }
        });

        if (liveEvent !== "input") {
            field.addEventListener("input", () => {
                if (touched.has(name)) {
                    validateAndPaint(name);
                }
            });
        }
    }

    fields.pais.addEventListener("change", syncCountry);
    fields.mensaje.addEventListener("input", () => {
        context.hidden = true;
    });

    root.addEventListener("input", () => {
        if (status.dataset.state === "ok") {
            clearStatus();
        }
    });

    root.addEventListener("submit", (event) => {
        void send(event);
    });

    // --- Prefill desde la URL (?equipo=, ?produccion=) y aviso tras redirección ---
    const params = new URLSearchParams(window.location.search);
    const equipo = params.get("equipo")?.trim().slice(0, 160) ?? "";
    const sector = params.get("produccion") ?? "";

    if (equipo !== "" && topic) {
        if (topic.value === "") {
            topic.value = `Me interesa recibir información sobre ${equipo}. `;
        }
        context.textContent = `Tu consulta: ${equipo}`;
        context.hidden = false;
    }

    if (sector === "avicultura") {
        fields.produccion.value = "Avicultura";
    } else if (sector === "porcicultura") {
        fields.produccion.value = "Porcicultura";
    } else if (sector === "both") {
        fields.produccion.value = "Avicultura y porcicultura";
    }

    const originParts = ["page=inicio"];
    if (equipo !== "") {
        originParts.push(`equipo=${equipo}`);
    }
    if (sector !== "") {
        originParts.push(`sector=${sector}`);
    }
    origin.value = originParts.join("; ").slice(0, 160);

    syncCountry();
    renderedAt.value = String(Date.now());

    const estado = params.get("estado");
    if (estado === "ok") {
        showStatus("ok", "Recibimos tu consulta. Te responderemos al correo que indicaste.");
    } else if (estado === "error") {
        showFallback("No pudimos completar el envío. Inténtalo de nuevo o escríbenos directamente por correo.");
    }
}
