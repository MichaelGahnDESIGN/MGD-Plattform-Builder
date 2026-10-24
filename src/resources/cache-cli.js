import { refreshOfflineCache } from './offline-cache.js';

// Interner Wartungsaufruf; Pfad stammt aus der privaten Serverkonfiguration.
try {
  let input='';for await(const part of process.stdin){input+=part;if(input.length>8192)throw new Error('Eingabe zu groß');}
  const {databaseDirectory}=JSON.parse(input);
  if(typeof databaseDirectory!=='string' || !databaseDirectory.startsWith('/') || databaseDirectory.length>1024)throw new Error('Ungültiger Datenbankpfad');
  process.stdout.write(JSON.stringify(await refreshOfflineCache(databaseDirectory)));
} catch { process.stdout.write(JSON.stringify({status:'failed',reason:'Offline-Datenbank konnte nicht aktualisiert werden.'}));process.exitCode=1; }
