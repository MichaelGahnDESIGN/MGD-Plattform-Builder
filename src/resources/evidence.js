/** Fremde Scannerberichte sind Hinweise; nur passende, aktuelle Nachweise verwenden. */
export function evidenceState(c, now=Date.now()) {
  if(c.scope!=='scanner-import')return null;
  const age=now-Date.parse(c.scannerTimestamp);
  const recent=age>=0 && age<7*86400000;
  const subject=typeof c.scannerSubject==='string' && /^(?:sha256:)?[a-f0-9]{64}$/i.test(c.scannerSubject);
  return {status:recent&&subject?'reported':'unchecked',reason:!recent?'Scannerbericht fehlt, ist veraltet oder liegt in der Zukunft.':!subject?'Image-/Artefakt-Digest fehlt.':'Aktueller Fremdbericht mit Digest; Herkunft und Einsatz separat bestätigen.',checkedAt:c.scannerTimestamp||null};
}
