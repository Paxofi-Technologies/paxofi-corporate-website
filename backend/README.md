# Backend

Pure PHP 8+ backend boundary for the Paxofi Corporate Website, integrated with the approved Paxofi Core Framework (PCF) contract.

Application layers follow the canonical architecture:

`HTTP/Routes → Middleware → Controllers/Handlers → Application Services → Domain/Policies → Repositories → Database/Adapters`

PCF internals are not duplicated in this repository.
