/**
 * Vercel serverless: reçoit le JSON du formulaire Manufa et crée une page
 * dans une base Notion (Site internet MANUFA).
 *
 * Variables d'environnement Vercel :
 *   NOTION_API_KEY  — Secret de l’intégration Notion
 *   NOTION_DATABASE_ID — ID de la base (créée dans la page Notion)
 *
 * Base Notion : propriétés requises
 *   - Titre (title)
 *   - Date (date)
 *   - Contenu (rich_text)
 */

const NOTION_API = "https://api.notion.com/v1";
const NOTION_VERSION = "2022-06-28";

function truncate(str, max) {
    if (typeof str !== "string") str = String(str);
    return str.length <= max ? str : str.slice(0, max - 3) + "...";
}

function buildNotionProperties(payload) {
    const title = payload.general?.name_company || "Sans nom";
    const date = payload.general?.event_date || null;
    const summary = [
        payload.general?.name_company && `Entreprise: ${payload.general.name_company}`,
        payload.general?.event_date && `Date: ${payload.general.event_date}`,
        payload.general?.location && `Lieu: ${payload.general.location}`,
        payload.event?.event_type && `Type: ${payload.event.event_type}`,
        payload.contact?.email && `Email: ${payload.contact.email}`,
        (payload.general?.contact_number || payload.contact?.phone) && `Tél: ${payload.general?.contact_number || payload.contact?.phone}`,
    ].filter(Boolean).join(" | ");
    const fullJson = JSON.stringify(payload, null, 2);
    const content = summary + "\n\n---\n\n" + truncate(fullJson, 1900);

    const props = {
        Titre: {
            title: [{ text: { content: truncate(title, 200) } }],
        },
        Contenu: {
            rich_text: [{ text: { content: truncate(content, 2000) } }],
        },
    };

    if (date) {
        props.Date = { date: { start: date } };
    }

    return props;
}

export default async function handler(req, res) {
    if (req.method !== "POST") {
        res.setHeader("Allow", "POST");
        return res.status(405).json({ ok: false, message: "Method not allowed" });
    }

    const apiKey = process.env.NOTION_API_KEY;
    const databaseId = process.env.NOTION_DATABASE_ID;

    if (!apiKey || !databaseId) {
        return res.status(500).json({
            ok: false,
            message: "NOTION_API_KEY ou NOTION_DATABASE_ID manquant (config serveur).",
        });
    }

    let payload;
    try {
        payload = typeof req.body === "string" ? JSON.parse(req.body) : req.body;
    } catch (_) {
        return res.status(400).json({ ok: false, message: "Body JSON invalide." });
    }

    if (!payload || typeof payload !== "object") {
        return res.status(400).json({ ok: false, message: "Body invalide." });
    }

    const properties = buildNotionProperties(payload);

    try {
        const notionRes = await fetch(`${NOTION_API}/pages`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": `Bearer ${apiKey}`,
                "Notion-Version": NOTION_VERSION,
            },
            body: JSON.stringify({
                parent: { database_id: databaseId.replace(/-/g, "") },
                properties,
            }),
        });

        const data = await notionRes.json().catch(() => ({}));

        if (!notionRes.ok) {
            const msg = data.message || data.code || `Notion API ${notionRes.status}`;
            return res.status(502).json({
                ok: false,
                message: "Erreur Notion: " + msg,
            });
        }

        return res.status(200).json({
            ok: true,
            message: "Demande envoyée ✅",
            id: data.id,
        });
    } catch (err) {
        return res.status(500).json({
            ok: false,
            message: err.message || "Erreur serveur.",
        });
    }
}
