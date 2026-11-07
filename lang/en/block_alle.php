<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings for ALL-E.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['adobe_client_id'] = 'Adobe PDF Services client ID';
$string['adobe_client_id_desc'] = 'Optional. Used to read PDFs that pdftotext cannot, such as scanned documents.';
$string['adobe_client_secret'] = 'Adobe PDF Services client secret';
$string['adobe_client_secret_desc'] = 'Client secret paired with the client ID above.';
$string['alle:addinstance'] = 'Add an ALL-E block';
$string['alle:ask'] = 'Ask questions to the course assistant';
$string['alle:managecontent'] = 'Edit the prompt and the documents of the course assistant';
$string['askquestion'] = 'Ask your question...';
$string['backtosettings'] = 'Back to the settings';
$string['chathistory'] = 'Conversation with the assistant';
$string['check_adobe_ok'] = 'The credentials are accepted.';
$string['check_adobe_skipped'] = 'Not configured: only pdftotext will be used.';
$string['check_embeddings'] = 'Embeddings';
$string['check_embeddings_ok'] = '{$a->provider} answered with vectors of {$a->dimensions} dimensions.';
$string['check_generation'] = 'Text generation';
$string['check_generation_ok'] = '{$a->provider} answered: {$a->answer}';
$string['check_pdftotext_missing'] = 'pdftotext was not found at "{$a}".';
$string['check_pdftotext_ok'] = 'pdftotext found at "{$a}".';
$string['check_weaviate_ok'] = 'Connected; {$a} collection(s) found.';
$string['collection_prefix'] = 'Collection prefix';
$string['collection_prefix_desc'] = 'Start of the name of the Weaviate collection of each course. Give each Moodle site its own prefix when several sites share one Weaviate cluster. Changing it means documents must be uploaded again.';
$string['defaultprompt'] = 'Situation Context
The learner is taking a course on [[ coursename ]]. Your role is to support them by providing accurate, relevant, and helpful answers adapted to their learning.

Mission
As an assistant, your mission is to help the learner understand concepts from the course on [[ coursename ]] by answering their questions using the provided context. [[ history ]]
You must provide clear, precise, and relevant responses, and only share information that comes from the course. If the answer cannot be found in the provided context, strictly respond with: "I am calibrated based on the course content carefully selected by your teacher. If you need more information, we encourage you to contact them."

If the learner writes phrases showing they have not understood a concept or a previous explanation, check [[ history ]] to identify what was misunderstood, then rephrase your explanation more simply with clearer examples.

Instructions
1. Analyze the new question only (not the earlier messages) to detect emotional cues using the following taxonomy:
   - Confusion: "I don\'t understand", "I\'m lost", "It\'s unclear".
   - Frustration: "This is annoying", "I give up", "It\'s too hard".
   - Stress/Anxiety: "I\'m stressed", "I\'m overwhelmed", "There\'s no time left".
   - Doubt/Insecurity: "I can\'t do this", "I\'m not good enough".

2. Only if one of these cues appears in the new question, begin your answer with an empathetic phrase:
   - Confusion: "I understand, it\'s not easy."
   - Frustration: "I see it\'s frustrating."
   - Stress: "It\'s normal to feel overwhelmed sometimes."
   - Doubt: "You\'re doing your best, and that\'s already great."

   If no cue appears, do not open with an empathetic or reassuring phrase: answer the question directly.

3. Reply clearly and with structure.
4. Use examples where needed.
5. Never give answers outside the provided context.

6. Integrate the 4 components of empathy (as defined in https://link.springer.com/article/10.1007/s00146-023-01715-z/tables/1):
   - Cognitive: Show understanding of the learner\'s perspective and reformulate to confirm. Suggest ideas linked to what they said without imposing.
   - Affective: Be emotionally aware, validate their feelings, use kind words or simple metaphors.
   - Attitudinal: Warm, respectful, and supportive tone. Encourage effort, show openness, avoid judgment.
   - Attunement: Adapt language and style to the learner. Let them guide the discussion.

7. Maintain a kind, respectful, and motivating tone throughout the exchange.

New learner question: [[ question ]]';
$string['dropfiles'] = 'Drop your files here or click to choose them';
$string['editprompt'] = 'Edit the prompt';
$string['embedding_model_desc'] = 'Model that turns documents and questions into vectors. After changing it, documents must be uploaded again in every course.';
$string['error:badresponse'] = 'Unexpected answer from {$a}.';
$string['error:filetoobig'] = 'The file is larger than {$a}.';
$string['error:filetype'] = 'Only PDF, TXT and Markdown files are accepted.';
$string['error:fileunreadable'] = 'The file is empty or could not be read.';
$string['error:http'] = '{$a->host} answered with HTTP error {$a->status}: {$a->detail}';
$string['error:nocontent'] = 'No course document has been indexed yet. Ask your teacher to upload the course documents.';
$string['error:notconfigured'] = 'ALL-E is not fully configured: "{$a}" is missing.';
$string['error:pdfextraction'] = 'The PDF could not be read by Adobe PDF Services ({$a}).';
$string['error:pdfnotreadable'] = 'No text could be read from this PDF. If it is a scan, configure Adobe PDF Services.';
$string['error:unreachable'] = '{$a->host} could not be reached: {$a->detail}';
$string['error:vectorstore'] = 'Weaviate error: {$a}';
$string['heading_pdf'] = 'Reading PDF documents';
$string['heading_pdf_desc'] = 'pdftotext is tried first; Adobe PDF Services is used for the PDFs it cannot read.';
$string['heading_provider'] = 'AI provider';
$string['heading_provider_desc'] = 'The same provider generates answers and embeds documents, so that course documents and questions share one vector space.';
$string['heading_rag'] = 'Answers';
$string['heading_weaviate'] = 'Weaviate vector database';
$string['heading_weaviate_desc'] = 'Course documents are stored in Weaviate, one collection per course.';
$string['historyanswer'] = 'Answer:';
$string['historyheading'] = 'History of previous interactions:';
$string['historyquestion'] = 'Previous question {$a}:';
$string['huggingface_api_key'] = 'Hugging Face access token';
$string['huggingface_api_key_desc'] = 'Token of the Hugging Face account, or of your own inference server.';
$string['huggingface_embedding_endpoint'] = 'Hugging Face embedding URL';
$string['huggingface_embedding_endpoint_desc'] = 'Feature extraction endpoint; %s is replaced by the model name. Point it to your own server to keep documents on premises.';
$string['huggingface_embedding_model'] = 'Hugging Face embedding model';
$string['huggingface_endpoint'] = 'Hugging Face generation URL';
$string['huggingface_endpoint_desc'] = 'OpenAI compatible chat completions endpoint. Point it to your own Text Generation Inference server to keep questions on premises.';
$string['huggingface_model'] = 'Hugging Face generation model';
$string['huggingface_model_desc'] = 'Instruction-tuned model, for instance mistralai/Mistral-7B-Instruct-v0.3.';
$string['index'] = 'Index';
$string['indexed'] = '{$a->files} document(s) indexed ({$a->passages} passages). I will now answer from them.';
$string['indexing'] = 'Indexing the course documents...';
$string['openrouter_api_key'] = 'OpenRouter API key';
$string['openrouter_api_key_desc'] = 'Key from openrouter.ai.';
$string['openrouter_embedding_model'] = 'OpenRouter embedding model';
$string['openrouter_model'] = 'OpenRouter generation model';
$string['openrouter_model_desc'] = 'Model identifier as listed on openrouter.ai, for instance openai/gpt-4o or anthropic/claude-sonnet-4.5.';
$string['pathtopdftotext'] = 'Path to pdftotext';
$string['pathtopdftotext_desc'] = 'pdftotext is part of poppler-utils. Leave empty to use Adobe PDF Services only.';
$string['pluginname'] = 'ALL-E';
$string['privacy:conversations'] = 'Conversations';
$string['privacy:metadata:adobepdf'] = 'When pdftotext cannot read a PDF, the file uploaded by the teacher is sent to Adobe PDF Services to extract its text.';
$string['privacy:metadata:adobepdf:file'] = 'The PDF document.';
$string['privacy:metadata:conversations'] = 'The latest questions of each learner in a course, with the answers, so the assistant remembers the conversation.';
$string['privacy:metadata:conversations:answer'] = 'The answer of the assistant.';
$string['privacy:metadata:conversations:courseid'] = 'The course.';
$string['privacy:metadata:conversations:question'] = 'The question of the learner.';
$string['privacy:metadata:conversations:timecreated'] = 'When the question was asked.';
$string['privacy:metadata:conversations:userid'] = 'The learner.';
$string['privacy:metadata:embeddings'] = 'Questions are sent to the embedding model of the configured provider (OpenRouter or Hugging Face) to search the course documents.';
$string['privacy:metadata:embeddings:question'] = 'The question of the learner.';
$string['privacy:metadata:languagemodel'] = 'Questions are sent to the language model of the configured provider (OpenRouter or Hugging Face) to produce the answers.';
$string['privacy:metadata:languagemodel:history'] = 'The latest questions and answers of the learner in the course.';
$string['privacy:metadata:languagemodel:prompt'] = 'The prompt of the course.';
$string['privacy:metadata:languagemodel:question'] = 'The question of the learner.';
$string['privacy:metadata:prompts'] = 'The prompt written by the teachers of a course.';
$string['privacy:metadata:prompts:courseid'] = 'The course.';
$string['privacy:metadata:prompts:prompt'] = 'The prompt text.';
$string['privacy:metadata:prompts:timemodified'] = 'When the prompt was last changed.';
$string['privacy:metadata:prompts:usermodified'] = 'The teacher who last changed the prompt.';
$string['privacy:metadata:vectorstore'] = 'Passages of the documents uploaded by teachers are stored in the configured Weaviate database. No learner data is sent to it.';
$string['privacy:metadata:vectorstore:courseid'] = 'The course the documents belong to.';
$string['privacy:prompt'] = 'Prompt';
$string['prompt'] = 'Prompt';
$string['prompthelp'] = 'The prompt frames every answer in this course. You can use [[ coursename ]], [[ question ]] and [[ history ]]. Save an empty prompt to restore the default one.';
$string['promptsaved'] = 'The prompt has been saved. Ask me a question to try it.';
$string['provider'] = 'Provider';
$string['provider_desc'] = 'OpenRouter gives access to many hosted models. Hugging Face also works with a self-hosted server for on-premise deployments.';
$string['provider_huggingface'] = 'Hugging Face';
$string['provider_openrouter'] = 'OpenRouter';
$string['rag_mode'] = 'Answering mode';
$string['rag_mode_agentic'] = 'Agentic RAG';
$string['rag_mode_desc'] = 'Standard RAG searches the course once and answers. Agentic RAG checks whether the passages found are close enough to the question, rewrites the question when they are not, reranks the passages and cites its sources.';
$string['rag_mode_standard'] = 'Standard RAG';
$string['relevance_threshold'] = 'Relevance threshold';
$string['relevance_threshold_desc'] = 'Agentic RAG only. A passage counts as relevant when its cosine distance to the question is below this value; with fewer than two relevant passages, the question is rewritten. The right value depends on the embedding model: lower it to rewrite more often.';
$string['send'] = 'Send';
$string['testsettings'] = 'Check the ALL-E settings';
$string['testsettings_intro'] = 'Each configured service will be called once. Calls to AI providers may be charged.';
$string['testsettings_run'] = 'Check the services';
$string['uploaddocuments'] = 'Upload the course documents';
$string['uploadhelp'] = 'PDF, TXT or Markdown files, up to {$a} each. The new documents replace the ones currently indexed for this course.';
$string['weaviate_api_key'] = 'Weaviate API key';
$string['weaviate_api_key_desc'] = 'Leave empty for a Weaviate server without authentication.';
$string['weaviate_url'] = 'Weaviate URL';
$string['weaviate_url_desc'] = 'For instance https://my-cluster.weaviate.cloud.';
$string['welcomelearner'] = 'Hello! I am ALL-E, your learning assistant for this course. I can explain concepts, help you review and clarify difficult points. What would you like to know?';
$string['welcometeacher'] = 'Hello! Upload the course documents and adjust the prompt, then ask me questions to check how I answer your learners.';
