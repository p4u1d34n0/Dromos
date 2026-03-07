# PHP Coding Paradigms

## General
- Follow PSR-12 coding style
- Use strict type declarations where the project uses them
- Keep methods focused and short
- Handle edge cases gracefully — never let invalid input crash the application

## Error Handling
- Validate inputs before processing
- Use early returns for guard clauses
- Skip invalid data rather than throwing exceptions in non-critical paths (like config loading)

## Code Style
- Match the existing code style in the file
- Use meaningful variable names
- Keep changes minimal and focused
