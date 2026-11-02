// Copia el backend PHP a dist/api y genera la configuración desde los Secrets
// de GitHub Actions. Se ejecuta después de `astro build`.

import { chmod, copyFile, mkdir, readFile, readdir, rm, stat, writeFile } from "node:fs/promises";
import { existsSync } from "node:fs";
import path from "node:path";
import process from "node:process";

const root = process.cwd();
const source = path.join(root, "api");
const target = path.join(root, "dist", "api");

/** Escapa un valor para una cadena de PHP entre comillas simples. */
const phpString = (value) => `'${String(value).replace(/\\/g, "\\\\").replace(/'/g, "\\'")}'`;

const phpArray = (entries, indent = 4) => {
    const pad = " ".repeat(indent);
    const lines = entries.map(([key, value]) => `${pad}${phpString(key)} => ${value},`);
    return `[\n${lines.join("\n")}\n${" ".repeat(indent - 4)}]`;
};

const phpList = (values, indent = 8) => {
    const pad = " ".repeat(indent);
    return `[\n${values.map((value) => `${pad}${phpString(value)},`).join("\n")}\n${" ".repeat(indent - 4)}]`;
};

async function copyDir(from, to, relative = "") {
    const entries = await readdir(from, { withFileTypes: true });

    for (const entry of entries) {
        const rel = path.join(relative, entry.name);
        const sourcePath = path.join(from, entry.name);
        const targetPath = path.join(to, entry.name);

        if (entry.isDirectory()) {
            // El almacenamiento se recrea vacío: nunca se suben cachés ni logs.
            if (rel === "storage") {
                await mkdir(targetPath, { recursive: true });
                continue;
            }
            await mkdir(targetPath, { recursive: true });
            await copyDir(sourcePath, targetPath, rel);
            continue;
        }

        if (rel === "config.php" || rel === "config.example.php") {
            continue;
        }

        await copyFile(sourcePath, targetPath);
    }
}

function generatedConfig(env) {
    const serviceAccount = JSON.parse(env.SHEETS_SA_JSON);

    if (!serviceAccount.client_email || !serviceAccount.private_key) {
        throw new Error("SHEETS_SA_JSON no contiene client_email/private_key");
    }

    const origins = (env.ALLOWED_ORIGINS ?? "https://afgrantec.com,https://www.afgrantec.com")
        .split(",")
        .map((value) => value.trim())
        .filter(Boolean);

    const config = phpArray([
        ["timezone", phpString(env.APP_TIMEZONE ?? "America/Bogota")],
        ["dry_run", "false"],
        [
            "mail",
            phpArray(
                [
                    ["from_name", phpString("AFGRANTEC")],
                    ["to", phpString(env.MAIL_TO ?? env.GRAPH_SENDER)],
                    [
                        "bcc",
                        phpList(
                            (env.MAIL_BCC ?? "nicolas.oliveros@afgrantec.com,fernando.munoz@afgrantec.com")
                                .split(",")
                                .map((value) => value.trim())
                                .filter(Boolean),
                        ),
                    ],
                    ["subject_prefix", phpString("Consulta AFGRANTEC")],
                ],
                8,
            ),
        ],
        [
            "graph",
            phpArray(
                [
                    ["tenant_id", phpString(env.GRAPH_TENANT_ID)],
                    ["client_id", phpString(env.GRAPH_CLIENT_ID)],
                    ["client_secret", phpString(env.GRAPH_CLIENT_SECRET)],
                    ["sender", phpString(env.GRAPH_SENDER)],
                ],
                8,
            ),
        ],
        [
            "sheets",
            phpArray(
                [
                    ["spreadsheet_id", phpString(env.SHEETS_ID)],
                    ["sheet_name", phpString(env.SHEETS_TAB ?? "Consultas")],
                    [
                        "service_account",
                        phpArray(
                            [
                                ["client_email", phpString(serviceAccount.client_email)],
                                ["private_key", phpString(serviceAccount.private_key)],
                            ],
                            12,
                        ),
                    ],
                ],
                8,
            ),
        ],
        [
            "security",
            phpArray(
                [
                    ["allowed_origins", phpList(origins, 12)],
                    ["salt", phpString(env.APP_SALT)],
                    [
                        "rate_limit",
                        phpArray(
                            [
                                ["window", "600"],
                                ["max", "5"],
                                ["global_window", "3600"],
                                ["global_max", "30"],
                            ],
                            12,
                        ),
                    ],
                ],
                8,
            ),
        ],
        ["consent_version", phpString("v1-2026-11")],
    ]);

    return `<?php\n\ndeclare(strict_types=1);\n\n// Generado por scripts/build-api.mjs. No editar a mano.\nreturn ${config};\n`;
}

async function main() {
    if (!existsSync(source)) {
        throw new Error("No existe la carpeta api/");
    }

    await rm(target, { recursive: true, force: true });
    await mkdir(target, { recursive: true });

    await copyDir(source, target);

    // Configuración: Secrets (CI) > config.php local > aviso.
    const required = [
        "GRAPH_TENANT_ID",
        "GRAPH_CLIENT_ID",
        "GRAPH_CLIENT_SECRET",
        "GRAPH_SENDER",
        "SHEETS_SA_JSON",
        "SHEETS_ID",
        "APP_SALT",
    ];
    const missing = required.filter((key) => !process.env[key]);
    const isCi = process.env.GITHUB_ACTIONS === "true" || process.env.CI === "true";

    if (missing.length === 0) {
        await writeFile(path.join(target, "config.php"), generatedConfig(process.env), "utf8");
        console.log("api/config.php generado desde variables de entorno.");
    } else if (existsSync(path.join(source, "config.php"))) {
        await copyFile(path.join(source, "config.php"), path.join(target, "config.php"));
        console.log("api/config.php copiado desde el archivo local (desarrollo).");

        const localConfig = await readFile(path.join(source, "config.php"), "utf8");
        if (/'dry_run'\s*=>\s*true/.test(localConfig)) {
            console.warn(
                "Aviso: el build incluye dry_run=true (no envía correo ni escribe en Sheets). " +
                    "En producción la configuración se genera desde los Secrets de GitHub.",
            );
        }
    } else if (isCi) {
        throw new Error(`Faltan secrets para generar api/config.php: ${missing.join(", ")}`);
    } else {
        console.warn(
            `Aviso: no se generó dist/api/config.php (faltan ${missing.join(", ")}). ` +
                "El formulario responderá 500 en este build.",
        );
    }

    // Asegura permisos de escritura del almacenamiento en el hosting.
    const storage = path.join(target, "storage");
    await mkdir(storage, { recursive: true });
    if (existsSync(path.join(source, "storage", ".htaccess"))) {
        await copyFile(path.join(source, "storage", ".htaccess"), path.join(storage, ".htaccess"));
    }

    try {
        const info = await stat(storage);
        await chmod(storage, 0o755);
        console.log(`dist/api listo (${info.isDirectory() ? "directorio" : "archivo"} de almacenamiento).`);
    } catch {
        console.log("dist/api listo.");
    }
}

main().catch((error) => {
    console.error(error instanceof Error ? error.message : error);
    process.exitCode = 1;
});
