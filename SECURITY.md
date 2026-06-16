# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| Latest (`main`) | ✅ |
| Older releases | ❌ (upgrade recommended) |

## Reporting a Vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**

Report security issues by email to the maintainer. Include:
- A description of the vulnerability
- Steps to reproduce
- Potential impact
- Any suggested mitigations

You will receive a response within 72 hours. We aim to release a fix within 14 days for critical issues.

## Security Model

See [README_SECURITY.md](README_SECURITY.md) for a detailed description of the platform's security model, including role-based access control, encryption at rest, audit logging, and hardening guidance.

## Scope

In scope:
- Authentication bypasses
- Authorization/IDOR vulnerabilities
- Data leakage between users or tenants
- Remote code execution
- SQL injection, XSS, CSRF

Out of scope:
- Vulnerabilities requiring physical access to the server
- Issues in third-party dependencies without a clear exploit path
- Rate limiting on non-sensitive endpoints
- Self-XSS
