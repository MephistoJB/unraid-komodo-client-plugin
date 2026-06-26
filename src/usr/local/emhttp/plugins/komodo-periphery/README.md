# Komodo Periphery for Unraid

Native Unraid plugin for running Komodo Periphery as a host service.

- No Docker container for the agent itself
- Persistent config in `/boot/config/plugins/komodo-periphery/`
- Persistent keys and runtime config in `/boot/config/komodo/periphery-agent/`
- Service control through `/etc/rc.d/rc.komodo-periphery`
