// enrollAdminOrg2.js
const { Wallets } = require('fabric-network');
const FabricCAServices = require('fabric-ca-client');
const fs = require('fs');
const path = require('path');

async function enrollAdminOrg2() {
  try {
    // Load Org2 connection profile
    const ccpPath = path.resolve(__dirname, 'network', 'connection-org2.json');
    const ccp = JSON.parse(fs.readFileSync(ccpPath, 'utf8'));

    // Create a new CA client for Org2.
    const caURL = ccp.certificateAuthorities['ca.org2.example.com'].url;
    const ca = new FabricCAServices(caURL);

    // Create a new file system based wallet for managing identities.
    const walletPath = path.join(process.cwd(), 'wallet');
    const wallet = await Wallets.newFileSystemWallet(walletPath);
    console.log(`Wallet path: ${walletPath}`);

    // Check if the admin identity already exists.
    const identity = await wallet.get('admin-org2');
    if (identity) {
      console.log('An identity for the admin user "admin-org2" already exists in the wallet');
      return;
    }

    // Enroll the admin user with the CA.
    const enrollment = await ca.enroll({ enrollmentID: 'admin', enrollmentSecret: 'adminpw' });
    const x509Identity = {
      credentials: {
        certificate: enrollment.certificate,
        privateKey: enrollment.key.toBytes(),
      },
      mspId: 'Org2MSP', // Ensure this matches your Org2 MSP
      type: 'X.509',
    };
    await wallet.put('admin-org2', x509Identity);
    console.log('Successfully enrolled admin user "admin-org2" and imported it into the wallet');
  } catch (error) {
    console.error(`Failed to enroll admin for Org2: ${error}`);
  }
}

enrollAdminOrg2();
