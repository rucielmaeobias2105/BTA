import { appendFileSync, existsSync, mkdirSync } from "node:fs"
import { createHash } from "node:crypto"
import { dirname, join } from "node:path"

const LOG_FILE = "prompt.log"
const MARKDOWN_FILE = "PROMPT_LOG.md"
const MARKDOWN_HEADER = "# Prompt Log"

/** Guards against the same prompt being written twice (retries, permission re-asks). */
const seen = new Set()

function stamp(date) {
  const p = (n) => String(n).padStart(2, "0")
  return (
    `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())} ` +
    `${p(date.getHours())}:${p(date.getMinutes())}:${p(date.getSeconds())}`
  )
}

function append(path, text) {
  try {
    mkdirSync(dirname(path), { recursive: true })
    appendFileSync(path, text, "utf8")
  } catch (error) {
    // A logging failure must never break the session.
  }
}

/** Pulls the plain prompt text out of the user message parts. */
function extractText(parts) {
  if (!Array.isArray(parts)) return ""
  return parts
    .filter((part) => part && part.type === "text" && typeof part.text === "string")
    .map((part) => part.text)
    .join("\n")
    .trim()
}

function extractAttachments(parts) {
  if (!Array.isArray(parts)) return []
  return parts
    .filter((part) => part && part.type === "file")
    .map((part) => part.filename || part.mime || "attachment")
}

/** Builds a short, human-readable heading from the prompt body. */
function titleOf(text) {
  const first = text
    .split("\n")
    .map((line) => line.replace(/^[\s>*#-]+/, "").trim())
    .find((line) => line.length > 0)

  if (!first) return "Untitled prompt"

  const cleaned = first.replace(/[`*_[\]]/g, "").replace(/\s+/g, " ").trim()
  return cleaned.length > 72 ? `${cleaned.slice(0, 69)}...` : cleaned
}

function ensureMarkdown(path) {
  if (existsSync(path)) return
  try {
    mkdirSync(dirname(path), { recursive: true })
    appendFileSync(path, `${MARKDOWN_HEADER}\n`, "utf8")
  } catch (error) {
    // A logging failure must never break the session.
  }
}

export const PromptLoggerPlugin = async ({ directory, worktree }) => {
  const root = worktree || directory || process.cwd()
  const logPath = join(root, LOG_FILE)
  const markdownPath = join(root, MARKDOWN_FILE)

  ensureMarkdown(markdownPath)

  return {
    "chat.message": async (input, output) => {
      try {
        const text = extractText(output?.parts)
        const attachments = extractAttachments(output?.parts)

        if (!text && attachments.length === 0) return

        const key = createHash("sha1")
          .update(`${input?.messageID ?? ""}\u0000${text}\u0000${attachments.join(",")}`)
          .digest("hex")

        if (seen.has(key)) return
        seen.add(key)

        const at = stamp(new Date())
        const session = input?.sessionID ?? "unknown"
        const attachmentNote = attachments.length
          ? ` [attachments: ${attachments.join(", ")}]`
          : ""

        append(logPath, `[${at}] [PROMPT] ${text.replace(/\s*\n\s*/g, " ")}${attachmentNote}\n`)

        const body = attachments.length
          ? `${text}\n\nAttachments: ${attachments.join(", ")}`
          : text

        append(
          markdownPath,
          `\n### Task: ${titleOf(text)}\n* Prompt used: ${JSON.stringify(body)}\n` +
            `* Recorded: ${at} (session ${session})\n`,
        )
      } catch (error) {
        // Never surface logging errors to the user.
      }
    },
  }
}

export default PromptLoggerPlugin
