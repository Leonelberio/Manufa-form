/**
 * Test de connexion à la base Notion (exécuter en local avec la clé en variable d’environnement).
 * Usage: NOTION_API_KEY=ntn_xxx NOTION_DATABASE_ID=30038b0dfbe3809f9699f622dacf1ff4 node test-notion-db.js
 * Ne committez jamais NOTION_API_KEY dans le dépôt.
 */

const NOTION_API_KEY = process.env.NOTION_API_KEY;
const NOTION_DATABASE_ID = (process.env.NOTION_DATABASE_ID || "303f3f3c00fb801aaec6cfd6273491c2").replace(/-/g, "");

if (!NOTION_API_KEY) {
    console.error("Définir NOTION_API_KEY (clé secrète de l’intégration Notion).");
    process.exit(1);
}

const NOTION_API = "https://api.notion.com/v1";
const NOTION_VERSION = "2022-06-28";

async function testDatabase() {
    console.log("Test 1: Récupération de la base...");
    const res = await fetch(`${NOTION_API}/databases/${NOTION_DATABASE_ID}`, {
        method: "GET",
        headers: {
            "Authorization": `Bearer ${NOTION_API_KEY}`,
            "Notion-Version": NOTION_VERSION,
        },
    });
    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
        console.error("Erreur:", res.status, data.message || data.code || JSON.stringify(data));
        if (res.status === 404) console.error("Vérifiez que NOTION_DATABASE_ID est l’ID d’une base (pas d’une simple page).");
        if (res.status === 401) console.error("Clé invalide ou intégration non connectée à cette base.");
        process.exit(1);
    }

    console.log("Base trouvée:", data.title?.[0]?.plain_text || data.id);
    console.log("Propriétés:", Object.keys(data.properties || {}).join(", "));

    const required = ["Nom", "Date événement", "Notes"];
    const props = Object.keys(data.properties || {});
    const missing = required.filter((r) => !props.includes(r));
    if (missing.length) {
        console.warn("Attention: propriétés attendues:", required.join(", "));
        console.warn("Manquantes dans la base:", missing.join(", "));
    } else {
        console.log("Propriétés requises (Nom, Date événement, Notes) présentes.");
    }

    console.log("\nTest 2: Création d’une page de test...");
    const createRes = await fetch(`${NOTION_API}/pages`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Authorization": `Bearer ${NOTION_API_KEY}`,
            "Notion-Version": NOTION_VERSION,
        },
        body: JSON.stringify({
            parent: { database_id: NOTION_DATABASE_ID },
            properties: {
                "Nom": { title: [{ text: { content: "Test formulaire Manufa" } }] },
                "Date événement": { date: { start: new Date().toISOString().slice(0, 10) } },
                "Notes": { rich_text: [{ text: { content: "Page créée par le script de test. Vous pouvez la supprimer." } }] },
            },
        }),
    });
    const createData = await createRes.json().catch(() => ({}));

    if (!createRes.ok) {
        console.error("Erreur création page:", createRes.status, createData.message || JSON.stringify(createData));
        if (createData.code === "validation_error") console.error("Détail:", createData.message);
        process.exit(1);
    }

    console.log("Page de test créée:", createData.id);
    console.log("\n✅ Connexion à la base Notion OK. Vous pouvez supprimer la page de test dans Notion.");
}

testDatabase().catch((err) => {
    console.error(err);
    process.exit(1);
});
