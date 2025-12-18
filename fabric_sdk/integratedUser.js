// integratedUser.js
const { Wallets, Gateway } = require('fabric-network');
const FabricCAServices = require('fabric-ca-client');
const fs = require('fs');
const path = require('path');
const { v4: uuidv4 } = require('uuid');

async function registerAndRetrieveClientId(org, role) {
  try {
    let ccpPath, caName, mspId, adminIdentity;
    if (org === 'org1') {
      ccpPath = path.resolve(__dirname, 'network', 'connection-org1.json');
      caName = 'ca.org1.example.com';
      mspId = 'Org1MSP';
      adminIdentity = 'admin-org1';
    } else if (org === 'org2') {
      ccpPath = path.resolve(__dirname, 'network', 'connection-org2.json');
      caName = 'ca.org2.example.com';
      mspId = 'Org2MSP';
      adminIdentity = 'admin-org2';
    } else {
      throw new Error('Invalid organization specified. Use "org1" or "org2".');
    }

    const ccp = JSON.parse(fs.readFileSync(ccpPath, 'utf8'));
    const caURL = ccp.certificateAuthorities[caName].url;
    const ca = new FabricCAServices(caURL);

    const walletPath = path.join(process.cwd(), 'wallet');
    const wallet = await Wallets.newFileSystemWallet(walletPath);

    const admin = await wallet.get(adminIdentity);
    if (!admin) {
      throw new Error(`Admin identity ${adminIdentity} not found in wallet. Enroll admin first.`);
    }
    
    const provider = wallet.getProviderRegistry().getProvider(admin.type);
    const adminUser = await provider.getUserContext(admin, adminIdentity);

    const userID = uuidv4();
    const secret = await ca.register({
      affiliation: org === 'org1' ? 'org1.department1' : 'org2.department1',
      enrollmentID: userID,
      role: 'client',
      attrs: [{ name: 'role', value: role, ecert: true }]
    }, adminUser);

    const enrollment = await ca.enroll({ enrollmentID: userID, enrollmentSecret: secret });
    const x509Identity = {
      credentials: {
        certificate: enrollment.certificate,
        privateKey: enrollment.key.toBytes(),
      },
      mspId: mspId,
      type: 'X.509',
    };
    await wallet.put(userID, x509Identity);

    const gateway = new Gateway();
    await gateway.connect(ccp, { wallet, identity: userID, discovery: { enabled: true, asLocalhost: true } });
    const network = await gateway.getNetwork('mychannel');
    const contract = network.getContract('trafficviolation');
    const clientID = (await contract.evaluateTransaction('GetClientID')).toString();
    await gateway.disconnect();

    return { userID, clientID };
  } catch (error) {
    throw error;
  }
}

module.exports = { registerAndRetrieveClientId };
