# Neuron Secrets Management

## Overview

Neuron provides Rails-style encrypted credentials management to securely store sensitive configuration like database passwords, API keys, and other secrets. Secrets are encrypted using AES-256-CBC with HMAC authentication and can be safely committed to version control.

## Quick Start

### 1. Generate an Encryption Key

```bash
# Generate master key
neuron secrets:key:generate

# Generate environment-specific key
neuron secrets:key:generate --env=production
```

This creates a 256-bit encryption key at:
- `config/master.key` for base secrets
- `config/secrets/production.key` for environment-specific secrets

**⚠️ IMPORTANT:** Never commit key files to version control! Add them to `.gitignore` immediately.

### 2. Edit Secrets

```bash
# Edit base secrets (uses $EDITOR or vi by default)
neuron secrets:edit

# Edit with specific editor
neuron secrets:edit --editor=nano

# Edit environment-specific secrets
neuron secrets:edit --env=production
```

This opens your editor with the decrypted YAML content. When you save and exit, the file is automatically re-encrypted.

### 3. View Secrets

```bash
# Show all secrets
neuron secrets:show

# Show specific key
neuron secrets:show --key=database

# Show production secrets (with confirmation)
neuron secrets:show --env=production

# Skip confirmation for production
neuron secrets:show --env=production --force
```

## File Structure

```
config/
├── master.key                    # Base encryption key (gitignored)
├── secrets.yml.enc               # Encrypted base secrets
└── secrets/
    ├── production.key            # Production key (gitignored)
    ├── production.yml.enc        # Encrypted production secrets
    ├── staging.key              # Staging key (gitignored)
    └── staging.yml.enc          # Encrypted staging secrets
```

## Environment Variables

Instead of key files, you can use environment variables:

```bash
# For master key
export NEURON_MASTER_KEY=your_64_char_hex_key

# For environment-specific keys
export NEURON_PRODUCTION_KEY=your_64_char_hex_key
export NEURON_STAGING_KEY=your_64_char_hex_key
```

The system automatically checks for environment variables if key files don't exist.

## CMS Installation Integration

When installing the CMS, use the `--use-secrets` flag to store sensitive configuration in encrypted files:

```bash
neuron cms:install --use-secrets
```

This will:
1. Generate a master key if one doesn't exist
2. Split configuration between public (`neuron.yaml`) and encrypted (`secrets.yml.enc`)
3. Automatically update `.gitignore` to exclude key files

### What Gets Encrypted

Public configuration (`neuron.yaml`):
- Application name, URL, environment
- Public settings like timezone, locale
- Non-sensitive service endpoints

Encrypted secrets (`secrets.yml.enc`):
- Database passwords
- API keys and tokens
- SMTP credentials
- Session secrets
- Any sensitive configuration

## Security Best Practices

### Key Management

1. **Never commit key files** - Always add `*.key` to `.gitignore`
2. **Use different keys per environment** - Don't share production keys with staging/dev
3. **Rotate keys periodically** - Generate new keys and re-encrypt when team members leave
4. **Secure key distribution** - Use password managers or secure channels to share keys

### Access Control

1. **Limit key access** - Only give production keys to authorized personnel
2. **Use environment variables in production** - Avoid key files on production servers
3. **Audit access** - Log who accesses encrypted secrets in production

### Git Best Practices

```gitignore
# Encryption keys - NEVER commit these!
/config/master.key
/config/secrets/*.key
/config/*.key
*.key

# But DO commit encrypted files
!/config/secrets.yml.enc
!/config/secrets/*.yml.enc
```

## Command Options

### secrets:key:generate

| Option | Short | Description |
|--------|-------|------------|
| `--env` | `-e` | Environment for the key (e.g., production, staging) |
| `--config` | `-c` | Config directory path (default: config) |
| `--force` | `-f` | Overwrite existing key file |
| `--show` | `-s` | Display the generated key |

### secrets:edit

| Option | Short | Description |
|--------|-------|------------|
| `--env` | `-e` | Environment to edit (default: base secrets) |
| `--editor` | | Editor to use (default: $EDITOR or vi) |
| `--config` | `-c` | Config directory path (default: config) |

### secrets:show

| Option | Short | Description |
|--------|-------|------------|
| `--env` | `-e` | Environment to show (default: base secrets) |
| `--key` | `-k` | Show only specific key/section |
| `--config` | `-c` | Config directory path (default: config) |
| `--force` | `-f` | Skip confirmation prompts |

## Troubleshooting

### "Key file not found"

**Problem:** The command can't find the encryption key.

**Solutions:**
1. Check if the key file exists at the expected location
2. Set the key as an environment variable
3. Use `--config` to specify the correct directory
4. Generate a new key with `secrets:key:generate`

### "Editor exited with error"

**Problem:** The editor command failed.

**Solutions:**
1. Check your `$EDITOR` environment variable
2. Use `--editor` to specify a different editor
3. Ensure the editor is installed and in your PATH
4. Try a simple editor like `nano` or `vi`

### "Cannot decrypt file"

**Problem:** The encrypted file can't be decrypted.

**Solutions:**
1. Verify you're using the correct key
2. Check if the encrypted file is corrupted
3. Ensure the key matches the one used for encryption
4. Try using the environment variable instead of key file

## Examples

### Development Setup

```bash
# Generate development key
neuron secrets:key:generate

# Add database credentials
neuron secrets:edit
# Add in editor:
# database:
#   host: localhost
#   username: myapp
#   password: dev_password_123
#   database: myapp_dev

# Verify secrets
neuron secrets:show --key=database
```

### Production Deployment

```bash
# On deployment server
export NEURON_PRODUCTION_KEY=<key_from_secure_storage>

# Verify secrets are accessible
neuron secrets:show --env=production --force

# Start application (secrets automatically loaded)
php artisan serve
```

### Team Collaboration

```bash
# Team lead generates keys
neuron secrets:key:generate
neuron secrets:key:generate --env=staging
neuron secrets:key:generate --env=production

# Share keys securely (e.g., via password manager)
# Each team member sets environment variables
export NEURON_MASTER_KEY=<shared_key>
export NEURON_STAGING_KEY=<shared_key>

# Team members can now work with secrets
neuron secrets:show
neuron secrets:edit --env=staging
```

## Migration from Plain Config

If you have existing plain configuration files:

1. Generate a master key:
   ```bash
   neuron secrets:key:generate
   ```

2. Create initial secrets file:
   ```bash
   neuron secrets:edit
   ```

3. Move sensitive values from plain config to secrets

4. Update application to read from both sources

5. Commit encrypted file, gitignore key:
   ```bash
   echo "/config/master.key" >> .gitignore
   git add config/secrets.yml.enc .gitignore
   git commit -m "Add encrypted secrets"
   ```

## Integration with Neuron Components

The secrets system integrates with various Neuron components:

- **CMS**: Database credentials, admin keys
- **Mail**: SMTP passwords, API tokens
- **Cache**: Redis passwords, connection strings
- **Queue**: Connection credentials
- **Storage**: S3 keys, CDN tokens

Each component automatically loads secrets when available, falling back to plain configuration for non-sensitive values.