/**
 * Envoie une soumission test (données type formulaire) vers la base Notion.
 * Usage: NOTION_API_KEY=xxx NOTION_DATABASE_ID=xxx node send-test-submission.js
 */
const NOTION_API_KEY = process.env.NOTION_API_KEY;
const NOTION_DATABASE_ID = (process.env.NOTION_DATABASE_ID || "303f3f3c00fb801aaec6cfd6273491c2").replace(/-/g, "");

if (!NOTION_API_KEY) {
    console.error("Définir NOTION_API_KEY.");
    process.exit(1);
}

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
        Nom: { title: [{ text: { content: truncate(title, 200) } }] },
        Notes: { rich_text: [{ text: { content: truncate(content, 2000) } }] },
    };
    if (date) props["Date événement"] = { date: { start: date } };
    return props;
}

const testPayload = {
    general: {
        name_company: "Manufa Test — Soumission",
        contact_number: "+33 6 12 34 56 78",
        event_date: "2025-03-15",
        location: "Paris, Grande salle",
        duration: "2h",
    },
    event: {
        event_type: "Conférence",
        platforms: ["YouTube", "LinkedIn"],
        simulcast: "Oui",
        qa_interaction: "Oui",
    },
    tech: {},
    logistics: {},
    post: {},
    contact: {
        email: "test@manufa.fr",
    },
};

async function send() {
    const properties = buildNotionProperties(testPayload);
    const res = await fetch(`${NOTION_API}/pages`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Authorization": `Bearer ${NOTION_API_KEY}`,
            "Notion-Version": NOTION_VERSION,
        },
        body: JSON.stringify({
            parent: { database_id: NOTION_DATABASE_ID },
            properties,
        }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        console.error("Erreur:", res.status, data.message || JSON.stringify(data));
        process.exit(1);
    }
    console.log("Soumission test envoyée.");
    console.log("Page créée:", data.id);
}

send().catch((err) => {
    console.error(err);
    process.exit(1);
});
