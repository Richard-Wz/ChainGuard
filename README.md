# ChainGuard

**Blockchain-Based Traffic Violation Logging and Penalty Management System**

## Overview

ChainGuard is a final year project leveraging blockchain technology to create a transparent, tamper-proof, and efficient system for logging traffic violations and managing penalties. The system is designed to enhance trust and accountability by securely recording all traffic violations on a blockchain ledger.

## Features

- **Blockchain Integration:** Ensures all violation records are immutable and transparent.
- **Violation Logging:** Authorities (admin) can securely log traffic violations.
- **Penalty Management:** Automated penalty calculation and management.
- **User Roles:**
  - **Authorities (Admin):** Manage violations and oversee all penalty operations.
  - **Drivers (Citizens):** View their own violation records and pay penalties.
- **Notifications:** Alert drivers about new violations and penalties.

## Tech Stack

- **Backend:** PHP, Go, Shell scripts
- **Frontend:** HTML, CSS, JavaScript
- **Blockchain:** Hyperledger Fabric (via Node.js SDK in `fabric_sdk`)
- **Containerization:** Docker, docker-compose
- **Other:** Express.js, Fabric SDK for Node.js

## Project Structure

- `/fabric_sdk`: Node.js backend for interacting with Hyperledger Fabric
- `/web`: PHP-based web frontend and backend
- `main.go`: Go backend logic (if applicable)
- `docker-compose.yml`: Docker Compose config for running services

## Setup

For setup instructions, please refer to the detailed guide provided in the installation kit (pdf).

**Quick Start:**  
Clone the repository and follow the instructions in the installation kit to deploy and run the system.

```bash
git clone https://github.com/Kae-RZ/FYP_81049_ChainGuard.git
cd FYP_81049_ChainGuard
```

## Service Links

Replace `<ip>` with your server IP to access these services:

- **Website:** [http://IP_address/](http://<ip>/)
- **Portainer (Docker Management):** [https://IP_address:9000/](https://<ip>:9000/)
- **Grafana (Monitoring):** [http://IP_address:3002/](http://<ip>:3002/)
- **CouchDB (Database UI):** [http://IP_address:5984/_utils/](http://<ip>:5984/_utils/)
- **Explorer (Blockchain Explorer):** [http://IP_address:8081](http://<ip>:8081)

## Usage

- **Authorities (Admin):**  
  Log in to record and manage traffic violations for all drivers.  
  View and manage all penalty records.
- **Drivers (Citizens):**  
  Log in to view their own violation and penalty records.  
  Pay penalties through the system.

## License

This project is licensed under the MIT License.

## Acknowledgments

- Hyperledger Fabric documentation
- Express.js and Node.js communities

---

> **Project by Richard Jong Wei Ze (81049) | UNIMAS | Network Computing | 2025**
