# Changes

## 1.0.0 (2026-11-07)

First release under the name ALL-E (`block_alle`). It replaces the
`block_uteluqchatbot` plugin, which is no longer maintained; data of that plugin
is not migrated.

- Agentic RAG mode: retrieval quality check, query rewriting, reranking and
  answers citing their sources. The relevance threshold is now a setting.
- OpenRouter and Hugging Face providers, with configurable Hugging Face
  endpoints for on-premise deployments.
- The teacher prompt now applies to every learner of the course.
- The conversation memory is now kept per course.
- Only users with `block/alle:managecontent` can replace the course documents.
- A document that cannot be read no longer wipes the documents already indexed.
- Fixed duplicated fragments at the end of each document when splitting text.
- All HTTP calls go through Moodle's curl class, with TLS verification.
- API keys are stored in password fields.
- Collection prefix setting, so several Moodle sites can share one Weaviate cluster.
- Settings check page for every configured service.
- Unit and Behat tests.
