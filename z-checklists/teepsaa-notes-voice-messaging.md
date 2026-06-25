# teepsaa — Voice-to-Text Khmer Messaging

Design notes for letting Khmer buyers/vendors communicate by voice memo while an
English-speaking admin replies in English — without anyone typing Khmer.

## The problem

Reddit/observed reality: Khmer customer service is often slow because **typing
Khmer is hard** (33 consonants, stacked/subscript coeng forms, dependent vowels,
diacritics — many keystrokes per syllable, cramped mobile keyboards). As a result
Khmer users lean on **voice memos** as their primary way to communicate.

Key insight: **typing Khmer is the problem; reading Khmer is not.** So a voice memo
here isn't "audio messaging" — it's a **replacement for the keyboard.**

## The design (voice-to-text, no TTS)

```
Buyer/Vendor: voice memo → Khmer text   (they READ it, fix if wrong, then send)
                              ↓ translate
Admin (me):   sees Khmer text + English
              type English → translate → Khmer
                              ↓
Buyer/Vendor: reads Khmer
```

- Customer **speaks** Khmer (no typing). ASR turns it into Khmer text.
- Customer **reads** the transcript and can re-record if it's wrong → fixes errors
  at the source, the cheapest place to fix them.
- The text (not audio) is what actually gets sent.
- Admin sees **both** Khmer and English. Admin types **English**; it's translated
  to Khmer.
- Customer **reads** the Khmer reply. No text-to-speech needed.

### Why this version is the right call

- **No TTS** — drops the most robotic/lowest-quality, most-likely-wrong component.
- **Self-correction is free** — native speaker proofreads their own transcript
  before sending, killing ASR errors at the source.
- Collapses to **three text operations**: Khmer voice→text (input only), plus
  text translation each direction. Cheaper, faster, fewer breakage points.

## The one real risk: translation quality

The customer reads fine — but they read **whatever the translator produced.** If
English→Khmer output is stiff or wrong, "reading isn't the problem" doesn't save
us. **Machine translation (both directions) is now the whole ballgame.**

Mitigations:

1. **Use a modern LLM for translation, not legacy word-for-word MT.** Claude's
   latest models do context-aware, colloquial Khmer far better; a support chat
   gives lots of context to lean on. The LLM can also clean up garbled ASR using
   context before translating.
2. **Keep the original Khmer voice memo stored** (don't send it — keep as a
   fallback). Admin already sees Khmer + English; if a translation looks off, the
   original can be replayed by me or a future part-time Khmer helper.

## Component options (verify before committing)

| Stage | Options | Notes |
| --- | --- | --- |
| Khmer ASR (voice→text) | Google Cloud Speech-to-Text (`km-KH`), self-hosted Whisper, Azure Speech | Google most reliable for Khmer; Whisper = no per-minute cost but weaker on Khmer |
| Translation (both ways) | **Claude (latest model)** preferred; Google Translate as fallback | LLM handles colloquial + ASR cleanup |
| TTS | **Not used** in this design | Output is text only |

> NOTE: Claude/LLMs cannot do the ASR step — they take text/vision, not audio.
> Transcription must go through a dedicated speech-to-text service. The LLM is only
> for the translation/cleanup step afterward.

## Strategic note

Even one **part-time Khmer speaker** as a spot-checker/backstop (not front line)
de-risks this a lot: automation handles the ~80% routine volume ("where's my
order"), a human catches the ~20% where a mistranslation could lose a sale. The
tech gives scale; one human gives safety. Recommend hybrid at launch, not 100%
automated.

## Fit with existing system

Slots into the current support messaging system (`support_messages` table; flows
in `messages-buyer/`, `messages-vendor/`, `admin/messages/`, polling via
`api/messages/poll.php`). A voice message is just a message whose input came from
audio. Likely additions:

- Recording UI via browser `MediaRecorder` (no library) next to the send button.
- Store original audio under an `/uploads/` path (served **through PHP** with the
  same per-thread/per-role access checks — never as guessable public URLs).
- New columns on `support_messages`, e.g.:
  - `original_lang_text` (Khmer transcript)
  - `translated_text` (English / Khmer counterpart)
  - `media_path` (original audio, fallback only)
  - `MODIFY body` nullable for non-text messages
- Server calls ASR → LLM (clean + translate) after saving; can run async so the
  sender doesn't wait.
- Storage cleanup: `ON DELETE CASCADE` removes DB rows but **not files** — needs
  cleanup logic.

## Open items to verify

- [ ] Real Khmer translation quality in both directions (draft test sentences,
      eyeball actual Khmer output) — this is the load-bearing piece.
- [ ] Khmer ASR accuracy on phone-quality voice memos (noisy, fast, code-switched
      with English loanwords).
- [ ] ASR provider choice: Google Cloud vs. self-hosted Whisper (cost vs. accuracy).
- [ ] Async vs. inline transcription/translation in the send path.
