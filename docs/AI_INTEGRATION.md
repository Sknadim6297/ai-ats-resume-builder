# AI Integration Preparation (Phase 1)

## Goals

Prepare a provider abstraction for Grok (xAI) without making live requests.

## Components

- `config/ai.php` — provider selection and task schema labels
- `App\Services\AI\AiManager` — Laravel Manager for drivers
- `AiProviderInterface` — `complete(AiRequest): AiResponse`
- `NullAiProvider` — default Phase 1 driver (throws intentionally)
- `GrokAiProvider` — configured skeleton; live HTTP deferred
- `AiProviderException` — structured error handling

## Environment

```
AI_PROVIDER=null
XAI_API_KEY=
XAI_BASE_URL=https://api.x.ai/v1
XAI_MODEL=grok-2-latest
XAI_TIMEOUT=60
XAI_MAX_RETRIES=2
```

Never expose `XAI_API_KEY` to React / `VITE_*` variables.

## Planned task contracts

| Task | Expected output |
|---|---|
| `jd_extraction` | Structured requirements JSON |
| `skill_matching` | Match / gap list vs master skills |
| `project_ranking` | Ordered project IDs with rationale |
| `resume_generation` | Grounded resume JSON from verified profile data only |

## Phase 1 rule

Calling `AiManager::complete()` must not hit the network. Providers throw
`AiProviderException::disabledInPhase('Phase 1')` until Phase 2 enables live calls.
