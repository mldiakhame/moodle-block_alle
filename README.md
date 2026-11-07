# ALL-E: Assistant for Lifelong Learning, intelligent and Empathic

[![Moodle Plugin CI](https://github.com/mldiakhame/moodle-block_alle/actions/workflows/ci.yml/badge.svg)](https://github.com/mldiakhame/moodle-block_alle/actions/workflows/ci.yml)

ALL-E is a Moodle block that gives each course an AI assistant. Learners ask
questions at any time and get answers drawn from the documents their teacher
selected, with the sources cited. Teachers choose those documents and adjust
the prompt that frames every answer.

ALL-E was developed by Université TÉLUQ (Canada) and Université Gaston Berger
de Saint-Louis (Senegal), with the support of the Agence universitaire de la
Francophonie (AUF).

## How it answers

When a teacher uploads course documents, ALL-E extracts their text, splits it
into overlapping passages of about 1,500 characters and stores the passages,
with their embeddings, in a Weaviate collection dedicated to the course.

Two answering modes are available:

- **Standard RAG** searches the course once and answers from the ten closest
  passages.
- **Agentic RAG** (default) checks whether the passages it found are close
  enough to the question. When they are not, it rewrites the question with the
  vocabulary of the course and searches again, at most twice. It then asks the
  language model to rank the passages and answers from the six best ones,
  citing them as `[Source N]`.

The default prompt asks the assistant to stay within the course, to recognise
signs of confusion, frustration, stress or doubt, and to answer with empathy.
The assistant remembers the last ten exchanges of each learner in each course.

## Requirements

- Moodle 4.5 or later.
- An AI provider for generation and embeddings, either:
  - [OpenRouter](https://openrouter.ai), which gives access to many hosted
    models, or
  - [Hugging Face](https://huggingface.co), or your own server exposing the
    same API, which keeps course content inside your network.
- A [Weaviate](https://weaviate.io) vector database, self-hosted or on
  Weaviate Cloud.
- To read PDFs: `pdftotext` from poppler-utils on the server, and optionally
  [Adobe PDF Services](https://developer.adobe.com/document-services/apis/pdf-services)
  for scanned documents.

## Installation

1. Copy the plugin to `blocks/alle` in your Moodle code (`public/blocks/alle`
   from Moodle 5.1), or install it from the Moodle plugins directory.
2. Visit **Site administration > Notifications** to complete the installation.
3. Fill in the settings under **Site administration > Plugins > Blocks > ALL-E**,
   then use **Check the services** to confirm that every service answers.
4. In a course, turn editing on and add the **ALL-E** block.
5. As a teacher, upload the course documents from the block.

## Settings

| Setting | Purpose |
| --- | --- |
| Answering mode | Standard or Agentic RAG. |
| Relevance threshold | Agentic RAG: cosine distance under which a passage counts as relevant (default 0.55). It depends on the embedding model; lower it to rewrite questions more often. |
| Provider | OpenRouter or Hugging Face, for both generation and embeddings. |
| Models | Generation and embedding models of the provider. BAAI bge-m3 is the default embedding model; it is multilingual. |
| Weaviate URL and key | Where course passages are stored. |
| Collection prefix | Keeps the collections of several Moodle sites apart in a shared Weaviate cluster. |
| Path to pdftotext, Adobe credentials | How PDF documents are read. |

Changing the embedding model or the collection prefix means documents must be
uploaded again in every course.

## Capabilities

| Capability | Default roles | Allows |
| --- | --- | --- |
| `block/alle:addinstance` | Editing teacher, manager | Adding the block to a course. |
| `block/alle:ask` | Student, teacher, editing teacher, manager | Chatting with the assistant. |
| `block/alle:managecontent` | Editing teacher, manager | Editing the prompt and replacing the course documents. |

## Privacy

ALL-E stores the last ten questions and answers of each learner in each course,
and the prompt of each course. Questions, the recent conversation and the
prompt are sent to the configured AI provider; course documents are stored in
the configured Weaviate database, and PDFs that pdftotext cannot read are sent
to Adobe PDF Services. The plugin implements the Moodle privacy API for export
and deletion.

## Development

Run the checks of the [Moodle plugin CI](https://moodlehq.github.io/moodle-plugin-ci/):
the code checker, PHPDoc, PHPUnit (`tests/`) and Behat (`tests/behat`). The
JavaScript is built with `grunt amd` from a Moodle checkout. See
[MAINTAIN.md](MAINTAIN.md).

Translations are managed through [AMOS](https://lang.moodle.org) once the
plugin is published; the plugin itself only ships the English strings.

## Research

The architecture and its evaluation are described in: A. Diattara,
M. L. Diakhame, V. Psyché, N. El Mawas, *From Standard RAG to Agentic
Orchestration: An Architecture for Moodle-Based Educational Conversational
Agents*, PA-AI&SS 2026, Springer LNICST.

## License

GNU GPL v3 or later. See [LICENSE](LICENSE).
