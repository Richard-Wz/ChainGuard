// enrollAdminOrg1.js
const { Wallets } = require('fabric-network');
const FabricCAServices = require('fabric-ca-client');
const fs = require('fs');
const path = require('path');

async function enrollAdminOrg1() {
  try {
    // Load Org1 connection profile
    const ccpPath = path.resolve(__dirname, 'network', 'connection-org1.json');
    const ccp = JSON.parse(fs.readFileSync(ccpPath, 'utf8'));

    // Create a new CA client for Org1.
    const caURL = ccp.certificateAuthorities['ca.org1.example.com'].url;
    const ca = new FabricCAServices(caURL);

    // Create a new file system based wallet for managing identities.
    const walletPath = path.join(process.cwd(), 'wallet');
    const wallet = await Wallets.newFileSystemWallet(walletPath);
    console.log(`Wallet path: ${walletPath}`);

    // Check if the admin identity already exists.
    const identity = await wallet.get('admin-org1');
    if (identity) {
      console.log('An identity for the admin user "admin-org1" already exists in the wallet');
      return;
    }

    // Enroll the admin user with the CA.
    const enrollment = await ca.enroll({ enrollmentID: 'admin', enrollmentSecret: 'adminpw' });
    const x509Identity = {
      credentials: {
        certificate: enrollment.certificate,
        privateKey: enrollment.key.toBytes(),
      },
      mspId: 'Org1MSP', // Ensure this matches your Org1 MSP
      type: 'X.509',
    };
    await wallet.put('admin-org1', x509Identity);
    console.log('Successfully enrolled admin user "admin-org1" and imported it into the wallet');
  } catch (error) {
    console.error(`Failed to enroll admin for Org1: ${error}`);
  }
}

enrollAdminOrg1();
