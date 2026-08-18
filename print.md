# teepsaa deploy — SSH setup

Replaces the old FTP deploy, which got the coffee-shop wifi IP banned by
Hostinger's firewall (the ban blocks every port, so the website itself goes
dark from that network — not just FTP).

Your server details, from hPanel → Advanced → SSH Access:

| | |
|---|---|
| IP | `194.164.64.128` |
| Port | `65002` |
| Username | `u767733958` |
| Status | ACTIVE |

---

## Already done for you

- SSH key created: `~/.ssh/teepsaa_deploy` (private) and
  `~/.ssh/teepsaa_deploy.pub` (public)
- `~/.ssh/config` written with a `teepsaa` shortcut, so you never type the
  IP, port, or username again
- `deploy-sftp.sh` created in the project root

## Step 1 — Paste the key into hPanel

On the SSH Access page, click **Add SSH key** and paste this:

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIDnnAEjb5Q0Z1Iw+G2ImdJqfR3XQAVrvMoZs56npEKkE teepsaa deploy
```

If you need it on the clipboard again:

```bash
pbcopy < ~/.ssh/teepsaa_deploy.pub
```

Paste it in hPanel rather than using `ssh-copy-id` — `ssh-copy-id` tries your
password first, and failed password attempts are one of the things that
triggers the IP ban.

## Step 2 — Turn on your phone hotspot

Connect the laptop to your phone, not the cafe wifi. The cafe IP is still
banned, so nothing below will work on it.

## Step 3 — Test the connection

```bash
ssh teepsaa
```

You should land on a shell prompt with no password asked. Type `exit` to
come back.

## Step 4 — Deploy

```bash
cd ~/Documents/programming/mywebsites/043\ teepsaa/teepsaa
./deploy-sftp.sh --dry-run
```

Read the file list. It must **not** contain any of these:

- `config/db.php`
- `config/smtp.php`
- `config/mapbox.php`
- `uploads/`

Those exist only on the server — production database, mail, and map
credentials, plus all user-uploaded images. If they show up, stop and fix the
excludes before uploading.

If the list looks right:

```bash
./deploy-sftp.sh
```

That's the whole deploy from now on. Two commands, no password, no ban.

---

## Notes

**Nothing is ever deleted on the server.** The script has no `--delete`, same
as the old FTP mirror. To remove a stale file from the server, delete it by
hand over SSH.

**The key has no passphrase**, which is what makes the deploy a single
command. It's still a big improvement on the old setup, where the FTP
password sat in plaintext in `deploycode.txt`. To add a passphrase later:

```bash
ssh-keygen -p -f ~/.ssh/teepsaa_deploy
```

**The old FTP script still works** as a fallback. `deploycode.txt` is
untouched apart from dropping `--parallel=10` to `--parallel=2`. Delete it
once SSH deploys are proven.

**If rsync fails saying the remote has no rsync binary**, switch the old
lftp line from `ftp://` to `sftp://` and port 21 to 65002. lftp speaks SFTP
natively, so you keep the familiar command and still get one encrypted
connection.

**If an IP gets banned again:** the giveaway is that the server drops every
port *and* ping from that network, while other websites load fine. Switch to
the hotspot to keep working; bans usually expire within hours, or Hostinger
support can clear a specific IP. Note the pre-launch password gate in
`.htaccess` is a separate ban trigger — repeated cancelled Basic-auth prompts
look like brute force. That risk goes away when the gate is removed at launch.
