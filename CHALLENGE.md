# Senior Backend Engineer Vending Machine Challenge

## OBJECTIVE
The goal is to model a vending machine and the state it must maintain during its operation.
How the actions are driven is intentionally left vague and is up to the candidate.
The challenge is intentionally simple in functionality so that engineering quality, architecture, maintainability and design decisions can be evaluated.
The solution should be designed as a foundation that other engineers could extend and maintain over time.

## ACCEPTED MONEY
The machine accepts:
- 0.05
- 0.10
- 0.25
- 1.00

## REQUIRED PRODUCTS
The machine must have at least three primary products:
- Water: 0.65
- Juice: 1.00
- Soda: 1.50

Each available item has:
- a count
- a price
- a selector

## VALID ACTIONS
The valid actions are:
- Insert 0.05
- Insert 0.10
- Insert 0.25
- Insert 1.00
- Return Coin
- GET Water
- GET Juice
- GET Soda
- SERVICE

SERVICE represents a service person opening the machine and setting:
- available change
- how many items are available

## VALID RESPONSES
The machine can return:
- 0.05
- 0.10
- 0.25
- Water
- Juice
- Soda

## MACHINE STATE
The machine must track:
- available items
- available change / number of coins available
- currently inserted money

## BEHAVIOUR
The machine:
- accepts only the supported denominations
- accepts money before product selection
- allows the customer to return all currently inserted money
- dispenses the selected item when enough money has been inserted
- returns change when more money than the product price was inserted
- must maintain product availability
- must maintain available change

The challenge does not prescribe the internal architecture or how actions are exposed to the user.

## EXAMPLES
Example 1:
1, 0.25, 0.25, GET-SODA
-> SODA

Example 2:
0.10, 0.10, RETURN-COIN
-> 0.10, 0.10

Example 3:
1, GET-WATER
-> WATER, 0.25, 0.10

## WHAT IS BEING EVALUATED
The evaluation focuses on:
1. Architectural decisions
2. Code maintainability
3. Extensibility
4. Testing approach
5. Business logic modelling
6. Engineering principles

The solution should demonstrate production-ready engineering suitable for a growing codebase.
The challenge explicitly asks candidates to consider how the design handles:
- new products
- new functionality
- new business rules
- multiple engineers maintaining the codebase

Avoid both:
- under-engineering
- unnecessary over-engineering

The challenge is intentionally simple in functionality so that engineering decisions can be demonstrated.

## TECHNICAL REQUIREMENTS
- Programming language: PHP
- Dockerfile or docker-compose is highly appreciated
- Comprehensive test coverage is expected
- PHPUnit or another appropriate testing solution may be used
- Libraries and tools may be used when they provide value
- The challenge values design decisions rather than reinventing existing tools

## AI-ASSISTED DEVELOPMENT
AI tools are explicitly allowed.
The candidate must be able to:
- defend architectural decisions
- explain trade-offs
- understand the submitted code
- discuss alternative approaches
- modify and extend the implementation without AI
AI should be treated as a pair-programming/development assistant rather than a replacement for engineering judgement.

## SUBMISSION REQUIREMENTS
The solution must be uploaded to a public:
- GitHub
- GitLab
- Bitbucket

repository.
The repository must contain a README.md with:
- requirements
- instructions for running the solution
The name of the company from the original challenge must not appear anywhere in the submitted code.
The Git history matters.
Commit from the beginning and commit often.
The implementation must be defensible in a technical interview, including architectural decisions, alternatives, trade-offs and extension scenarios.

## SOURCE OF TRUTH
This file represents the functional requirements of the challenge.
Do not silently add business requirements that are not specified here.
When the specification is ambiguous, identify the ambiguity and make an explicit, documented design decision rather than pretending the specification is more precise than it actually is.