import fs from "fs";
import path from "path";
import { spawnSync } from "child_process";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, "..");
const packageJsonPath = path.join(projectRoot, "package.json");
const textDomain = "woocommerce-gateway-ecommerceconnect";

const projectName = "eCommerceConnect Gateway";
const lastTranslator = "Serhii Pimenov";
const languageTeam = "UPC ECommerce";

function fail(message) {
  console.error(message);
  process.exit(1);
}

function runCommand(command, args) {
  const result = spawnSync(command, args, {
    cwd: projectRoot,
    stdio: "inherit",
    shell: process.platform === "win32",
  });

  if (result.error) {
    if (result.error.code === "ENOENT") {
      fail(`Command not found: ${command}. Install required CLI tools and try again.`);
    }
    fail(result.error.message);
  }

  if (result.status !== 0) {
    fail(`Command failed (${command} ${args.join(" ")})`);
  }
}

function commandExists(command, probeArgs = ["--version"]) {
  const result = spawnSync(command, probeArgs, {
    cwd: projectRoot,
    stdio: "ignore",
    shell: process.platform === "win32",
  });

  if (result.error) {
    return false;
  }

  return result.status === 0;
}

function getPackageVersion() {
  if (!fs.existsSync(packageJsonPath)) {
    fail(`package.json not found: ${packageJsonPath}`);
  }

  const pkg = JSON.parse(fs.readFileSync(packageJsonPath, "utf8"));
  if (!pkg.version || typeof pkg.version !== "string") {
    fail("Missing or invalid version in package.json");
  }

  return pkg.version;
}

function parseCliArgs(argv) {
  const action = argv[0];
  let srcDirOverride = null;

  for (let i = 1; i < argv.length; i += 1) {
    const arg = argv[i];

    if (arg === "--src-dir") {
      const value = argv[i + 1];
      if (!value) {
        fail("Missing value for --src-dir");
      }

      srcDirOverride = path.resolve(value);
      i += 1;
      continue;
    }

    fail(`Unknown option: ${arg}`);
  }

  return { action, srcDirOverride };
}

function resolvePaths(srcDirOverride) {
  const srcDir = srcDirOverride || path.join(projectRoot, "src");
  const langDir = path.join(srcDir, "languages");
  const potFile = path.join(langDir, "ecommerceconnect.pot");

  if (!fs.existsSync(srcDir)) {
    fail(`Source directory not found: ${srcDir}`);
  }

  return {
    srcDir,
    langDir,
    potFile,
  };
}

function getPoFiles(paths) {
  if (!fs.existsSync(paths.langDir)) {
    fail(`Languages directory not found: ${paths.langDir}`);
  }

  return fs
    .readdirSync(paths.langDir)
    .filter((file) => file.endsWith(".po"))
    .map((file) => path.join(paths.langDir, file));
}

function upsertHeaderLine(content, key, value) {
  const headerLine = `"${key}: ${value}\\n"`;
  const escapedKey = key.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const re = new RegExp(`"${escapedKey}:.*\\\\n"`, "m");

  if (re.test(content)) {
    return content.replace(re, headerLine);
  }

  return content.replace(/msgstr ""\r?\n/, (m) => `${m}${headerLine}\n`);
}

function normalizePoHeaders(poFile, version) {
  let content = fs.readFileSync(poFile, "utf8");

  content = upsertHeaderLine(content, "Project-Id-Version", `${projectName} ${version}`);
  content = upsertHeaderLine(content, "Last-Translator", lastTranslator);
  content = upsertHeaderLine(content, "Language-Team", languageTeam);

  fs.writeFileSync(poFile, content, "utf8");
}

function normalizePotHeader(paths, version) {
  if (!fs.existsSync(paths.potFile)) {
    return;
  }

  let content = fs.readFileSync(paths.potFile, "utf8");
  content = upsertHeaderLine(content, "Project-Id-Version", `${projectName} ${version}`);
  fs.writeFileSync(paths.potFile, content, "utf8");
}

function updatePot(paths, version) {
  console.log("[i18n] Updating POT file...");

  runCommand("wp", [
    "i18n",
    "make-pot",
    paths.srcDir,
    paths.potFile,
    `--domain=${textDomain}`,
    "--exclude=languages",
  ]);

  normalizePotHeader(paths, version);
}

function updatePoAll(paths, version) {
  if (!fs.existsSync(paths.potFile)) {
    fail(`POT file not found: ${paths.potFile}. Run i18n:update-pot first.`);
  }

  const poFiles = getPoFiles(paths);
  if (poFiles.length === 0) {
    fail(`No .po files found in ${paths.langDir}`);
  }

  console.log(`[i18n] Updating ${poFiles.length} PO files from POT...`);

  for (const poFile of poFiles) {
    runCommand("msgmerge", ["--update", "--backup=none", poFile, paths.potFile]);
    normalizePoHeaders(poFile, version);
  }
}

function compileMoAll(paths) {
  const poFiles = getPoFiles(paths);
  if (poFiles.length === 0) {
    fail(`No .po files found in ${paths.langDir}`);
  }

  console.log(`[i18n] Compiling ${poFiles.length} MO files...`);

  for (const poFile of poFiles) {
    const moFile = poFile.replace(/\.po$/, ".mo");
    runCommand("msgfmt", ["-o", moFile, poFile]);
  }
}

function buildForPackaging(paths, version) {
  const hasWp = commandExists("wp", ["--info"]);
  const hasMsgmerge = commandExists("msgmerge");
  const hasMsgfmt = commandExists("msgfmt");

  if (!hasMsgfmt) {
    fail("Command not found: msgfmt. Install GNU gettext and try again.");
  }

  if (hasWp && hasMsgmerge) {
    updatePot(paths, version);
    updatePoAll(paths, version);
  } else {
    const missing = [
      !hasWp ? "wp" : null,
      !hasMsgmerge ? "msgmerge" : null,
    ]
      .filter(Boolean)
      .join(", ");

    console.warn(
      `[i18n] Skipping POT/PO refresh because required tool(s) are unavailable: ${missing}.`
    );
    console.warn("[i18n] Continuing with MO compilation from existing PO files.");
  }

  compileMoAll(paths);
}

function main() {
  const { action, srcDirOverride } = parseCliArgs(process.argv.slice(2));
  const paths = resolvePaths(srcDirOverride);
  const version = getPackageVersion();

  if (srcDirOverride) {
    console.log(`[i18n] Using custom source directory: ${paths.srcDir}`);
  }

  switch (action) {
    case "update-pot":
      updatePot(paths, version);
      break;
    case "update-po-all":
      updatePoAll(paths, version);
      break;
    case "compile-mo-all":
      compileMoAll(paths);
      break;
    case "all":
      updatePot(paths, version);
      updatePoAll(paths, version);
      compileMoAll(paths);
      break;
    case "build":
      buildForPackaging(paths, version);
      break;
    default:
      fail("Usage: node scripts/i18n.mjs <update-pot|update-po-all|compile-mo-all|all|build> [--src-dir <path>]");
  }

  console.log("[i18n] Done.");
}

main();