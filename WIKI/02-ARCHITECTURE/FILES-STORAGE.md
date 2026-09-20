# Files and Storage

## Separate metadata from binary storage

Database rows should describe files, while the binary object can live in local storage, object storage or another managed backend.

Recommended metadata:

- asset ID
- owner/entity relation
- storage key
- original filename when needed
- MIME type
- size
- checksum
- created time
- visibility/access class
- processing state
- retention class

## Do not trust filenames

Use server-generated storage keys.

Validate:

- MIME
- file signature
- size
- dimensions/duration where relevant
- allowed extensions

## Public vs protected assets

Public assets may be served directly.

Private/protected assets should use an authorization-aware delivery path or short-lived signed access.

## Derivatives

Keep a clear distinction between:

- original
- normalized source
- generated derivative
- thumbnail/preview
- temporary processing file

## Deletion

Deleting metadata without deleting the binary object creates orphaned personal data.

Deleting the binary object while records still reference it creates corruption.

Use an explicit lifecycle.
