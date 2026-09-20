import path from "node:path";
import { fileURLToPath } from "node:url";

const currentFile = fileURLToPath(import.meta.url);
const currentDir = path.dirname(currentFile);

export const foundationRoot = path.resolve(currentDir, "../..");
export const resolveProjectPath = (input = ".") => path.resolve(process.cwd(), input);
