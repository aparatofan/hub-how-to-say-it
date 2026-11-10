# Exercise format

Each chapter's exercises are one JSON list, pasted into **HTSI HUB → Chapters → Exercises and key**. One object per exercise, in the order they appear on the printed page. The key is built from the answers inside, so there is no separate key to type.

Every exercise has:

| Field | Meaning |
| --- | --- |
| `type` | `choice`, `gapfill`, `upgrade` or `speaking` |
| `tag` | Short label, e.g. `EX 1` |
| `title` | e.g. `What is it doing?` |
| `instruction` | The instruction from the printed page |

In any dialogue line:

- start with `A: ` or `B: ` to get a speaker label;
- wrap the target expression in `**two stars**` to highlight it.

Plain text only. HTML is removed when you save.

In the key notes (`note`, `why`, `key_intro`, `checklist`), wrap a quoted word or phrase in `_underscores_` to show it in italics.

The key shows the answers and the notes to everyone. All note fields are optional.

## `choice` — EX 1, "What is it doing?"

```json
{
  "type": "choice", "tag": "EX 1", "title": "What is it doing?",
  "instruction": "Read each mini-dialogue. …",
  "items": [
    {
      "lines": ["A: Hi, Anna! How’s the new flat?", "B: **Can’t complain.** The neighbours are quiet."],
      "options": ["saying things are going well", "complaining politely", "changing the subject"],
      "answer": 0,
      "note": "_Complain_ is in the phrase, but it means things are fine."
    }
  ],
  "note_label": "Why the tempting option is wrong"
}
```

In the key, each item shows the bold expression, the correct option and the `note`. `note_label` is the small heading above each note (default: "Note").

`answer` counts from 0: `0` = a, `1` = b, `2` = c.

## `gapfill` — EX 2, "Choose the right response"

```json
{
  "type": "gapfill", "tag": "EX 2", "title": "Choose the right response",
  "instruction": "Complete each dialogue …",
  "bank": ["Is this seat taken?", "Safe travels!", "Speak soon!"],
  "dialogues": [
    { "title": "Dialogue 1 — Hotel breakfast", "lines": ["A: Excuse me — {1} Every other table’s full.", "A: Perfect. {2}"] }
  ],
  "answers": { "1": "Is this seat taken?", "2": "Speak soon!" },
  "near_misses": [
    { "expression": "Safe travels!", "tempting_for": "Gap 7", "why": "Nobody is travelling." }
  ]
}
```

A gap is `{number}`. Each answer must match a `bank` entry exactly, letter for letter. Bank entries with no gap are shown in the key as "Not needed". `near_misses` adds a "Near-misses" list to the key.

## `upgrade` — EX 3, "Upgrade the reply"

```json
{
  "type": "upgrade", "tag": "EX 3", "title": "Upgrade the reply",
  "instruction": "B’s reply works, but …",
  "items": [
    {
      "lines": ["A: Anna? It must be two years!", "B: **How are things?** Are you still at the bank?"],
      "hint": "it’s been ages — ask about the whole time since",
      "suggested": ["How have you been?"],
      "note": "It’s been two years, so ask about the whole time since you last met."
    }
  ],
  "key_intro": "Suggested answers. Accept any blue expression from the mind map that fits the hint."
}
```

The reader types a new opener and can show the suggested answer. `suggested` may list several; they are shown separated by " / ". Nothing is marked right or wrong.

## `speaking` — EX 4

```json
{
  "type": "speaking", "tag": "EX 4", "title": "Act it out",
  "instruction": "Work with a partner. …",
  "lead": "You are:",
  "prompts": ["bumping into a former classmate by chance", "finishing a meeting with a new client"],
  "key_intro": "Open task, no key. While pairs speak, check that each conversation:",
  "checklist": ["uses at least four expressions from the mind map;", "includes at least two blue expressions;"]
}
```

`lead` is optional. The key shows `key_intro` and the `checklist`; without them it shows "Speaking task. There is no key."
