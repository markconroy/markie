# LLM: Video to Text

## Field it applies to

- **Field type:** `text_long`
- **Potential target:** Any long text field on content entities

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmVideoToTextLong.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmVideoToTextLong.php?ref_type=heads)

## Description

The **LLM: Video to Text** rule automatically generates long text field content from video files.
It uses AI models to analyze video content, generate transcripts, and create descriptive text.
It combines audio transcription with video scene analysis to produce rich textual representations of the video’s content.

## Requirements

- **FFMpeg** must be installed on the server and accessible from the system path.
- A configured **speech-to-text provider** (e.g., Whisper or similar).

## Form fields required

- **Use text format**
  Type: `select`
  Description: Select a specific text format for the generated content. If the user who saves the content may not use it, the output is stored in the first format of the field they may use. Content saved without a logged-in user, for example from cron, runs as the [fallback user](../index.md#running-without-a-logged-in-user).
  Default (if not saved): `None`

- **Speech To Text Provider**
  A provider selector for speech-to-text processing of the audio.

## Example use cases

1. Automatically generate transcripts for recorded webinars.
2. Create summaries for training or instructional videos.
3. Produce video descriptions for accessibility purposes.
4. Generate textual documentation from video tutorials.
5. Create searchable text content from marketing videos.
6. Summarize event recordings for internal use.
7. Auto-generate captions or subtitles for videos.
8. Produce long-form content from product demo videos.

---

*This documentation was AI-generated.*
