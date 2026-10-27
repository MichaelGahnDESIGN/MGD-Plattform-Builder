import path from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const exec = promisify(execFile);
export class OfflineScannerFailure extends Error {
  constructor(reasonCode) { super('Offline-Prüfung unvollständig'); this.reasonCode = reasonCode; }
}

/** Nur nach der Binär-/Datenbankprüfung aufrufen; keine Projektdateien oder Resolver laden. */
export async function scanOfflineInventory(binary, directory, databaseDirectory, { run = exec } = {}) {
  const args = ['scan','source','--offline','--config='+path.join(directory,'osv-scanner.toml'),'--local-db-path='+databaseDirectory,'--no-resolve','--no-ignore','--no-call-analysis=go','--no-call-analysis=rust','--all-packages','--format=json','--verbosity=error','--x-no-default-plugins','--x-plugins=sbom',directory];
  let stdout;
  try {
    // Die große npm-Datenbank braucht GC-Spielraum; Zeit- und Ausgabegrenzen bleiben fest.
    ({ stdout } = await run(binary,args,{cwd:directory,env:{PATH:'/usr/bin:/bin',HOME:directory,GOMEMLIMIT:'384MiB'},timeout:32000,killSignal:'SIGKILL',maxBuffer:16*1024*1024,encoding:'utf8'}));
  } catch (e) {
    if (e.code === 1 && typeof e.stdout === 'string') stdout = e.stdout;
    else if (e.code === 'ERR_CHILD_PROCESS_STDIO_MAXBUFFER') throw new OfflineScannerFailure('offline-output-limit');
    else if (e.killed === true) throw new OfflineScannerFailure('offline-timeout');
    else throw new OfflineScannerFailure('offline-process-failed');
  }
  try { return JSON.parse(stdout); }
  catch { throw new OfflineScannerFailure('offline-response-incomplete'); }
}
