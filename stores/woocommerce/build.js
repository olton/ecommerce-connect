import crypto from "crypto";
import fs from "fs";
import path from "path";
import { spawnSync } from "child_process";
import { fileURLToPath } from "url";
import archiver from "archiver";

function readPackageVersion(packageJsonPath) {
	const raw = fs.readFileSync(packageJsonPath, "utf8");
	const data = JSON.parse(raw);

	if (!data.version || typeof data.version !== "string") {
		throw new Error("Missing or invalid version in package.json");
	}

	return data.version;
}

function ensureDir(dirPath) {
	fs.mkdirSync(dirPath, { recursive: true });
}

function clearDirContents(dirPath) {
	for (const entry of fs.readdirSync(dirPath)) {
		const fullPath = path.join(dirPath, entry);
		fs.rmSync(fullPath, { recursive: true, force: true });
	}
}

function createZipFromDir(srcDir, archivePath) {
	return new Promise((resolve, reject) => {
		const output = fs.createWriteStream(archivePath);
		const archive = archiver("zip", { zlib: { level: 9 } });

		output.on("close", resolve);
		output.on("error", reject);

		archive.on("warning", (error) => {
			if (error.code === "ENOENT") {
				console.warn(error.message);
				return;
			}

			reject(error);
		});

		archive.on("error", reject);
		archive.pipe(output);
		archive.directory(srcDir, false);
		archive.finalize();
	});
}

function runI18nAll(rootDir, stagedSrcDir) {
	const scriptPath = path.join(rootDir, "scripts", "i18n.mjs");
	const result = spawnSync(process.execPath, [scriptPath, "build", "--src-dir", stagedSrcDir], {
		cwd: rootDir,
		stdio: "inherit",
		shell: false,
	});

	if (result.status !== 0) {
		throw new Error("Failed to generate i18n artifacts for staging package");
	}
}

function ensureMoCoverage(stagedSrcDir) {
	const languagesDir = path.join(stagedSrcDir, "languages");
	if (!fs.existsSync(languagesDir)) {
		throw new Error(`Languages directory not found in staging: ${languagesDir}`);
	}

	const poFiles = fs.readdirSync(languagesDir).filter((file) => file.endsWith(".po"));
	if (poFiles.length === 0) {
		throw new Error(`No .po files found in staging languages directory: ${languagesDir}`);
	}

	const missingMoFiles = poFiles
		.map((poFile) => poFile.replace(/\.po$/, ".mo"))
		.filter((moFile) => !fs.existsSync(path.join(languagesDir, moFile)));

	if (missingMoFiles.length > 0) {
		throw new Error(
			`Missing compiled .mo files in staging package: ${missingMoFiles.join(", ")}. Run i18n build successfully before packaging.`
		);
	}
}

function calculateMd5(filePath) {
	const buffer = fs.readFileSync(filePath);
	return crypto.createHash("md5").update(buffer).digest("hex");
}

async function main() {
	const noClear = process.argv.includes("-no-clear");
	const __filename = fileURLToPath(import.meta.url);
	const rootDir = path.dirname(__filename);
	const packageJsonPath = path.join(rootDir, "package.json");
	const srcDir = path.join(rootDir, "src");
	const distDir = path.join(rootDir, "dist");
	const stagingRootDir = path.join(rootDir, ".build-staging");
	const stagingId = `${Date.now()}-${crypto.randomUUID().slice(0, 8)}`;
	const stagingDir = path.join(stagingRootDir, stagingId);
	const stagingSrcDir = path.join(stagingDir, "src");

	if (!fs.existsSync(srcDir)) {
		throw new Error(`Source directory not found: ${srcDir}`);
	}

	const version = readPackageVersion(packageJsonPath);
	const archiveName = `woocommerce-ecommerce-connect.${version}.zip`;
	const archivePath = path.join(distDir, archiveName);
	const md5Path = `${archivePath}.md5`;

	ensureDir(distDir);

	if (!noClear) {
		clearDirContents(distDir);
	}

	if (fs.existsSync(archivePath)) {
		fs.unlinkSync(archivePath);
	}

	if (fs.existsSync(md5Path)) {
		fs.unlinkSync(md5Path);
	}

	ensureDir(stagingRootDir);

	try {
		fs.cpSync(srcDir, stagingSrcDir, { recursive: true });
		runI18nAll(rootDir, stagingSrcDir);
		ensureMoCoverage(stagingSrcDir);
		await createZipFromDir(stagingSrcDir, archivePath);
	} finally {
		fs.rmSync(stagingDir, { recursive: true, force: true });
	}

	const md5 = calculateMd5(archivePath);
	fs.writeFileSync(md5Path, `${md5}\n`, "utf8");

	console.log(`Archive created: ${archivePath}`);
	console.log(`MD5 file created: ${md5Path}`);
	console.log(noClear ? "dist cleanup skipped (-no-clear)" : "dist cleaned before build");
}

try {
	main();
} catch (error) {
	console.error(error.message || error);
	process.exit(1);
}
