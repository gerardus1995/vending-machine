# Claude Code Development Guidelines for Vending Machine Challenge

## 1. PROJECT CONTEXT
This repository contains a Senior Backend Engineer technical challenge implementing a vending machine.
CHALLENGE.md contains the functional requirements and is the source of truth for the challenge.
The goal is not merely to make the examples work.
The implementation should demonstrate:
- strong domain modelling
- maintainability
- extensibility
- appropriate architecture
- high-quality testing
- clear engineering decisions
At the same time, avoid architecture astronautics.
The challenge explicitly values engineering depth, but every abstraction must have a concrete reason to exist.

## 2. TECHNOLOGY
Current technology:
- PHP 8.3
- Composer
- PHPUnit
- PHPStan
- PHP-CS-Fixer
- Docker / Docker Compose
This is currently a domain/CLI application.
Do not introduce Symfony unless explicitly requested.
Do not introduce:
- databases
- HTTP APIs
- message brokers
- queues
- ORM
- external infrastructure
unless a future requirement actually justifies them.

## 3. ARCHITECTURE
Prefer pragmatic domain-oriented architecture.
Keep business rules independent from infrastructure.
Model important business concepts explicitly when doing so provides real behaviour or protects meaningful invariants.
Avoid speculative abstractions.
Do not create:
- interfaces
- repositories
- factories
- strategies
- generic abstractions
- managers
- services
merely because they could theoretically be useful in the future.
Every abstraction must have a clear responsibility.
Prefer a small number of cohesive objects over a large hierarchy.

## 4. BUSINESS LOGIC
Business rules belong in the domain.
Avoid leaking business rules into the CLI.
The domain should be testable without Docker-specific or CLI-specific logic.
Operations that modify multiple pieces of state must not leave the machine in a partially modified state when a business operation fails.
When the specification is ambiguous:
1. identify the ambiguity
2. consider reasonable interpretations
3. choose a coherent behaviour
4. document the decision
5. test the chosen behaviour
Do not silently invent requirements.

## 5. MONEY
Never use floating-point arithmetic for monetary calculations.
Money should be represented internally using integer cents.
Do not introduce a third-party money library unless there is a compelling reason.

## 6. INVENTORY
Do not expose raw mutable arrays as the primary representation of important domain concepts when a dedicated abstraction provides meaningful behaviour or invariants.
However, do not create wrapper classes whose only purpose is hiding an array.
Every inventory abstraction must justify:
- its responsibility
- its invariants
- its public behaviour
- why it belongs in the domain

## 7. CHANGE CALCULATION
Change calculation should be isolated from vending machine orchestration.
The algorithm must respect:
- supported denominations
- available quantities
- requested change
The current denomination set is fixed by the challenge.
Do not introduce a complex algorithmic architecture without justification.
If the chosen algorithm has limitations for arbitrary future denomination systems, document the trade-off.

## 8. TESTING
Use PHPUnit.
Tests should primarily verify:
- business behaviour
- business invariants
- failure scenarios
- state transitions
Do not design production APIs solely to make tests easier.
Prefer real domain objects over mocks.
Do not optimize for a coverage percentage.
Optimize for confidence.
Use data providers when they improve readability.
Tests should follow Arrange / Act / Assert when appropriate.

## 9. CODE QUALITY
Use PHP 8.3 appropriately.
Prefer:
- strict_types
- explicit types
- readonly where appropriate
- final classes where appropriate
- immutable objects where appropriate
- cohesive classes
- meaningful names
Avoid:
- unnecessary comments
- speculative abstractions
- primitive obsession where a real domain concept exists
- excessive inheritance
- unnecessary design patterns

## 10. DEVELOPMENT PROCESS
Work incrementally.
Do not attempt to implement the entire architecture in a single operation.
For significant architectural changes:
1. Inspect the current implementation.
2. Explain the problem.
3. Propose the design.
4. Explain relevant alternatives and trade-offs.
5. Wait for approval when requested.
6. Implement only the agreed scope.
7. Run the relevant tests.
8. Run PHPStan.
9. Run PHP-CS-Fixer when appropriate.
10. Report exactly what changed.
Do not automatically continue into the next architectural phase.

## 11. CRITICAL THINKING
Do not blindly follow prompts.
If a proposed change would make the architecture worse, say so.
If the current implementation is better than the proposed solution, explain why.
If two approaches are reasonable, explain their trade-offs.
The objective is not to maximize the number of classes, interfaces or patterns.
The objective is to produce code that a senior engineer can confidently defend in a technical interview.

## 12. AI-ASSISTED DEVELOPMENT
AI is being used as a development assistant.
All generated code must be understandable and defensible by the developer.
Do not make large architectural changes without first establishing the design.
When there are multiple reasonable approaches, explain:
- why one was selected
- what alternatives were considered
- what trade-offs were accepted
Do not assume that generated code is correct simply because tests pass.

## 13. GIT
Git history is part of the evaluation.
Make small, meaningful, logically coherent commits.
Do not accumulate unrelated changes into one commit.
Do not squash the development history.
Do not rewrite existing history unless explicitly requested.
Before significant changes inspect:
- git status
- recent git history
Commit messages should describe the actual change.

## 14. CURRENT DEVELOPMENT STRATEGY
Development should happen in explicit phases.
Current priority:
1. Establish a sound Domain model.
2. Complete and validate Domain behaviour.
3. Implement Application/use-case orchestration.
4. Implement the CLI/interface layer.
5. Complete integration tests where useful.
6. Finalize README and developer experience.
7. Perform final architecture/code-quality review.
Do not start a later phase automatically.

## 15. DOCKER
Keep Docker simple.
The project should remain easy for an evaluator to run.
Do not introduce unnecessary containers or infrastructure.

## 16. CI
CI should remain focused on code quality and automated tests.
Do not introduce deployment infrastructure unless explicitly required.