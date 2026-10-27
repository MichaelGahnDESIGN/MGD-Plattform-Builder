import { refreshOfflineCache } from './offline-cache.js';
import { refreshPublicProvenance } from './provenance-cache.js';
import { refreshImageEvidence } from './images.js';

// Interner Wartungsaufruf; Pfad stammt aus der privaten Serverkonfiguration.
try {
  let input='';for await(const part of process.stdin){input+=part;if(input.length>8192)throw new Error('Eingabe zu groß');}
  const job=JSON.parse(input), directory=job.mode==='images'?job.images?.directory:job.mode==='public-provenance'?job.provenanceDirectory:job.databaseDirectory;
  if(typeof directory!=='string' || !directory.startsWith('/') || directory.length>1024)throw new Error('Ungültiger Datenbankpfad');
  process.stdout.write(JSON.stringify(job.mode==='images'?await refreshImageEvidence(job.images):job.mode==='public-provenance'?await refreshPublicProvenance(directory):await refreshOfflineCache(directory)));
} catch { process.stdout.write(JSON.stringify({status:'failed',reason:'Offline-Datenbank konnte nicht aktualisiert werden.'}));process.exitCode=1; }
