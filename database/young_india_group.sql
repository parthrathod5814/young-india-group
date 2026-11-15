-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 15, 2026 at 05:00 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `young_india_group`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `approve_donor` (IN `p_pending_id` INT UNSIGNED, IN `p_admin_id` INT UNSIGNED)   BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  INSERT INTO `donors` (`name`, `blood_group`, `phone`, `age`, `area`, `last_donation`, `status`, `approved_by`, `approved_at`)
    SELECT `name`, `blood_group`, `phone`, `age`, `area`, `last_donation`, 'Active', p_admin_id, NOW()
    FROM `pending_donors` WHERE `id` = p_pending_id;

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pending donor not found';
  END IF;

  DELETE FROM `pending_donors` WHERE `id` = p_pending_id;

  COMMIT;
  SELECT LAST_INSERT_ID() AS new_donor_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `approve_volunteer` (IN `p_pending_id` INT UNSIGNED, IN `p_admin_id` INT UNSIGNED)   BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  INSERT INTO `volunteers` (`name`, `phone`, `area`, `role`, `availability`, `status`, `approved_by`, `approved_at`)
    SELECT `name`, `phone`, `area`, `role`, `availability`, 'Approved', p_admin_id, NOW()
    FROM `pending_volunteers` WHERE `id` = p_pending_id;

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pending volunteer not found';
  END IF;

  DELETE FROM `pending_volunteers` WHERE `id` = p_pending_id;

  COMMIT;
  SELECT LAST_INSERT_ID() AS new_volunteer_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `id` int UNSIGNED NOT NULL,
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('Event','Donation & Charity','Blood Drive','Medical Camp','Education','Environment','Awareness') COLLATE utf8mb4_unicode_ci NOT NULL,
  `area` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Area / Taluka',
  `activity_date` date NOT NULL,
  `status` enum('Published','Draft','Unpublished') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `featured_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'HTML from the rich text editor. Clean it on the server before saving.',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`id`, `title`, `category`, `area`, `activity_date`, `status`, `featured_image`, `description`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'મોરબી યુવા કન્ક્લેવ: લીડરશીપ અને સામાજિક બ્લુપ્રિન્ટ', 'Event', 'Morbi City', '2026-11-19', 'Published', 'uploads/activities/3c916debd91f49bb0d2911e9214825ad.jpg', 'કન્ક્લેવમાં મોરબી શહેર ઉપરાંત માળિયા, હળવદ, ટંકારા અને વાંકાનેર તાલુકાના છેવાડાના ૮૫ થી વધુ ગામડાઓના ૩,૮૦૦થી વધુ સક્રિય યુવા પ્રતિનિધિઓ તથા સ્વયંસેવકો ઉપસ્થિત રહ્યા હતા. કાર્યક્રમના પ્રારંભમાં રાષ્ટ્રધ્વજ વંદના અને શહીદોની સ્મૃતિમાં પુષ્પાંજલિ અર્પણ કર્યા બાદ કાર્યકર્તાઓ દ્વારા સામાજિક એકતા, વ્યસનમુક્તિ અને નાગરિક સુરક્ષાના સંકલ્પ લેવાયા હતા.<br><u>સંમેલનમાં રક્તદાનના ક્ષેત્રે મોરબી જિલ્લાને ૨૪ કલાક આત્મનિર્ભર બનાવવા માટે વિશિષ્ટ ડિજિટલ નેટવર્કનું અનાવરણ કરાયું હતું. સિવિલ હોસ્પિટલ અને તમામ ટ્રસ્ટ હોસ્પિટલો સાથે સીધા સંકલનથી કોઈપણ ઈમરજન્સી કે અકસ્માત સમયે ત્વરિત રક્ત સહાય પહોંચાડવા ૧,૨૫૦થી વધુ યુવા દાતાઓનો ડેટાબેઝ સક્રિય કરવામાં આવ્યો હતો. આ ઉપરાંત છેવાડાના ગ્રામીણ વિસ્તારોમાં શિક્ષણ સહાય, આરોગ્ય તપાસ કેમ્પ તથા મહિલા સ્વાવલંબન માટેના માર્ગદર્શક પ્રકલ્પોની ઝીણવટભરી ચર્ચા હાથ ધરાઈ હતી.</u><br>પર્યાવરણ સંરક્ષણ અંતર્ગત મોરબીના ઔદ્યોગિક અને સિરામિક પટ્ટામાં ૧,૦૦,૦૦૦થી વધુ વૃક્ષોનું વાવેતર કરી આગામી બે વર્ષ સુધી તેનું સતત જતન કરવાની પ્રતિબદ્ધતા વ્યક્ત કરવામાં આવી. સમગ્ર કાર્યક્રમનું સંચાલન સંપૂર્ણપણે યુવા સ્વયંસેવકો દ્વારા શિસ્તબદ્ધ અને યોજનાબદ્ધ રીતે પૂર્ણ થયું હતું.', 1, '2026-11-14 10:40:27', '2026-11-14 11:41:50'),
(2, 'મોરબી યુવા કન્ક્લેવ: લીડરશીપ અને સામાજિક બ્લુપ્રિન્ટ', 'Medical Camp', 'Wankaner', '2026-11-12', 'Draft', 'uploads/activities/fb4c519ecd0576706f8dc92533b9b95c.png', 'દેવેન રબારીના નેતૃત્વમાં સૌરાષ્ટ્રના ૬૯ ગામડાઓમાંથી ૩,૮૦૦થી વધુ યુવા આગેવાનો દ્વારા સેવા અને કટોકટી સહાય માટે ત્રિવાર્ષિક યોજનાનું નિર્ધારણ કરવામાં આવ્યું.<br>', 1, '2026-11-14 10:43:09', '2026-11-14 10:43:09');

-- --------------------------------------------------------

--
-- Table structure for table `activity_gallery_images`
--

CREATE TABLE `activity_gallery_images` (
  `id` int UNSIGNED NOT NULL,
  `activity_id` int UNSIGNED NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_gallery_images`
--

INSERT INTO `activity_gallery_images` (`id`, `activity_id`, `image_path`, `sort_order`, `created_at`) VALUES
(1, 1, 'uploads/activities/8355f0fd12e9e62589591871b25501e2.jpg', 0, '2026-11-14 10:40:27'),
(2, 1, 'uploads/activities/5f73c835b4c645ad242c5172334a51e3.jpg', 1, '2026-11-14 10:40:27'),
(3, 1, 'uploads/activities/0e60d4e0c19ea44aceb4efb9a3620f61.png', 2, '2026-11-14 10:40:27'),
(4, 1, 'uploads/activities/8dbb4fcf868cf9c2afde0e210a86a336.jpg', 3, '2026-11-14 10:40:27'),
(5, 1, 'uploads/activities/8a4390833f7a023b7497a5142459f7d8.jpg', 4, '2026-11-14 10:40:27'),
(6, 1, 'uploads/activities/ab5a54e3c12d747c838aae18aea3e264.jpg', 5, '2026-11-14 10:40:27'),
(7, 2, 'uploads/activities/f7355d6dd7801a3a421e2d7239a0c1d5.jpg', 0, '2026-11-14 10:43:09'),
(8, 2, 'uploads/activities/412f6b415588175221c08e0418bb9738.jpg', 1, '2026-11-14 10:43:09'),
(9, 2, 'uploads/activities/408a1b8ab49fb30e3e2b75d8fad418f1.png', 2, '2026-11-14 10:43:09'),
(10, 2, 'uploads/activities/001ac4060d623a5983db478c8566ec45.jpg', 3, '2026-11-14 10:43:09'),
(11, 2, 'uploads/activities/33e3f929ea8c1aba42d409f43d7c8466.jpg', 4, '2026-11-14 10:43:09'),
(12, 2, 'uploads/activities/e191a74767568a895da44b70b8183a71.jpg', 5, '2026-11-14 10:43:09');

-- --------------------------------------------------------

--
-- Table structure for table `activity_translations`
--

CREATE TABLE `activity_translations` (
  `id` int UNSIGNED NOT NULL,
  `activity_id` int UNSIGNED NOT NULL,
  `language` enum('gu','en','hi') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_description` text COLLATE utf8mb4_unicode_ci,
  `context_background` mediumtext COLLATE utf8mb4_unicode_ci,
  `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_translations`
--

INSERT INTO `activity_translations` (`id`, `activity_id`, `language`, `title`, `short_description`, `context_background`, `description`, `image_caption`, `created_at`, `updated_at`) VALUES
(1, 1, 'gu', 'મોરબી યુવા કન્ક્લેવ: લીડરશીપ અને સામાજિક બ્લુપ્રિન્ટ', 'દેવેન રબારીના નેતૃત્વમાં સૌરાષ્ટ્રના ૬૯ ગામડાઓમાંથી ૩,૮૦૦થી વધુ યુવા આગેવાનો દ્વારા સેવા અને કટોકટી સહાય માટે ત્રિવાર્ષિક યોજનાનું નિર્ધારણ કરવામાં આવ્યું.', 'મોરબીના ઐતિહાસિક ટાઉનહોલ ખાતે <b>યંગ ઈન્ડિયા ગ્રુપ - દેવેન રબારી ફાઉન્ડેશન</b> દ્વારા આયોજિત ‘મોરબી યુવા કન્ક્લેવ ૨૦૨૫’ માત્ર એક સામાન્ય વિચારગોષ્ઠી નહોતી, પરંતુ સમર્પિત નાગરિક નેતૃત્વનું ગ્રાઉન્ડ લેવલ મંથન હતું. શહીદ ભગતસિંહ, સુખદેવ અને રાજગુરુની રાષ્ટ્રવાદી વિચારધારા તથા પૂજ્ય નાનુભાઈ ગીડના શિસ્તબદ્ધ સંસ્કારોને સાક્ષી રાખી મોરબી જિલ્લાભરના હજારો યુવાનો એક મંચ પર એકત્ર થયા હતા.<br>કન્ક્લેવના આરંભે સંસ્થાના સંસ્થાપક સ્વ. શ્રી દેવેનભાઈ એન. <i>ગીડ (રબારી)ના જીવનમંત્ર “પબ્લિસિટી નહીં, જરૂરિયાતમંદોની પ્રાયોરિટી”ને પુનરાવર્તિત</i> કરી આગામી સમય માટે ૭૯ જેટલા સેવા પ્રકલ્પોની સમયબદ્ધ રૂપરેખા જાહેર કરવામાં આવી હતી. આ અહેવાલ સમગ્ર મોરબી જિલ્લાના તમામ તાલુકાઓમાં સેવાની સુવ્યવસ્થિત વહેંચણી માટે માર્ગદર્શક આધાર બનશે.', 'કન્ક્લેવમાં મોરબી શહેર ઉપરાંત માળિયા, હળવદ, ટંકારા અને વાંકાનેર તાલુકાના છેવાડાના ૮૫ થી વધુ ગામડાઓના ૩,૮૦૦થી વધુ સક્રિય યુવા પ્રતિનિધિઓ તથા સ્વયંસેવકો ઉપસ્થિત રહ્યા હતા. કાર્યક્રમના પ્રારંભમાં રાષ્ટ્રધ્વજ વંદના અને શહીદોની સ્મૃતિમાં પુષ્પાંજલિ અર્પણ કર્યા બાદ કાર્યકર્તાઓ દ્વારા સામાજિક એકતા, વ્યસનમુક્તિ અને નાગરિક સુરક્ષાના સંકલ્પ લેવાયા હતા.<br><u>સંમેલનમાં રક્તદાનના ક્ષેત્રે મોરબી જિલ્લાને ૨૪ કલાક આત્મનિર્ભર બનાવવા માટે વિશિષ્ટ ડિજિટલ નેટવર્કનું અનાવરણ કરાયું હતું. સિવિલ હોસ્પિટલ અને તમામ ટ્રસ્ટ હોસ્પિટલો સાથે સીધા સંકલનથી કોઈપણ ઈમરજન્સી કે અકસ્માત સમયે ત્વરિત રક્ત સહાય પહોંચાડવા ૧,૨૫૦થી વધુ યુવા દાતાઓનો ડેટાબેઝ સક્રિય કરવામાં આવ્યો હતો. આ ઉપરાંત છેવાડાના ગ્રામીણ વિસ્તારોમાં શિક્ષણ સહાય, આરોગ્ય તપાસ કેમ્પ તથા મહિલા સ્વાવલંબન માટેના માર્ગદર્શક પ્રકલ્પોની ઝીણવટભરી ચર્ચા હાથ ધરાઈ હતી.</u><br>પર્યાવરણ સંરક્ષણ અંતર્ગત મોરબીના ઔદ્યોગિક અને સિરામિક પટ્ટામાં ૧,૦૦,૦૦૦થી વધુ વૃક્ષોનું વાવેતર કરી આગામી બે વર્ષ સુધી તેનું સતત જતન કરવાની પ્રતિબદ્ધતા વ્યક્ત કરવામાં આવી. સમગ્ર કાર્યક્રમનું સંચાલન સંપૂર્ણપણે યુવા સ્વયંસેવકો દ્વારા શિસ્તબદ્ધ અને યોજનાબદ્ધ રીતે પૂર્ણ થયું હતું.', 'મોરબી જિલ્લાના ૮૫ ગામડાઓમાંથી પધારેલા ૩,૮૦૦ થી વધુ યુવાનો દ્વારા ૨૪/૭ સેવા પ્રકલ્પોની શપથ વિધિ.', '2026-11-14 10:40:27', '2026-11-14 10:40:27'),
(2, 1, 'en', 'Morbi Youth Conclave: Leadership and Social Blueprint', 'Under the leadership of Deven Rabari, a three-year plan for service and emergency assistance was formulated by over 3,800 youth leaders from 69 villages in Saurashtra.', '<h2>‘Morbi Yuva Conclave 2025’</h2> (Morbi Youth Conclave 2025), <i>organized by the </i>Young India Group – Deven Rabari Foundation at the historic Town Hall of Morbi, was not merely an ordinary discussion forum; rather, it served as a grassroots-level brainstorming session for committed civic leadership. Inspired by the nationalist ideology of martyrs Bhagat Singh, Sukhdev, and Rajguru, and the disciplined values ​​instilled by the revered Nanubhai Gid, thousands of youths from across the Morbi district gathered on a single platform.<br>At the commencement of the conclave, the organization’s founding motto—championed by the late Shri Devenbhai N. Gid (Rabari)—“Prioritize the needy, not publicity,” was reaffirmed. A<b> time-bound roadmap comprising 79 service initiatives for the near future was also unveiled. This framework will serve as a guiding document to ensure the systematic distribution of service activities across all talukas of the Morbi dis</b>trict.', 'The conclave was attended by over 3,800 active youth representatives and volunteers from Morbi city, as well as from more than 85 remote villages across the Maliya, Halvad, Tankara, and Wankaner talukas. The event began with a salute to the national flag and the offering of floral tributes in memory of the martyrs; subsequently, the participants took pledges focused on social unity, de-addiction, and civic safety.<ul><li><br></li><li>During the gathering, a specialized digital network was unveiled, designed to make Morbi district self-reliant in blood donation—ensuring availability around the clock. A database of over 1,250 young donors was activated, integrated directly with the Civil Hospital and various trust-run hospitals, to facilitate the immediate provision of blood in the event of emergencies or accidents. </li></ul><br>Furthermore, detailed discussions were held regarding initiatives for remote rural areas, including educational support, health check-up camps, and programs aimed at fostering women&#039;s self-reliance.As part of environmental conservation efforts, a commitment was made to plant over 100,000 trees across Morbi’s industrial and ceramic belt, with a pledge to ensure their continued care and maintenance over the next two years. The entire event was managed and executed by young volunteers in a highly disciplined and well-planned manner.', 'A pledge-taking ceremony for 24/7 service initiatives by over 3,800 youths who arrived from 85 villages in the Morbi district.', '2026-11-14 10:40:27', '2026-11-14 11:41:50'),
(3, 1, 'hi', 'मोरबी जिले के 85 गांवों से आए 3,800 से अधिक युवाओं द्वारा 24/7 सेवा कार्यों के लिए संकल्प लेने का कार्यक्रम।', 'देवेन रबारी के नेतृत्व में, सौराष्ट्र के 69 गांवों के 3,800 से अधिक युवा नेताओं ने सेवा और आपातकालीन सहायता के लिए तीन साल की एक योजना तैयार की।', '&#039;मोरबी युवा कॉन्क्लेव 2025&#039;मोरबी के ऐतिहासिक टाउन हॉल में &#039;यंग इंडिया ग्रुप - देवेन रबारी फाउंडेशन&#039; द्वारा आयोजित &#039;मोरबी युवा कॉन्क्लेव 2025&#039; केवल एक सामान्य चर्चा मंच नहीं था, बल्कि यह समर्पित नागरिक नेतृत्व के लिए जमीनी स्तर पर मंथन करने का एक सत्र था। शहीद भगत सिंह, सुखदेव और राजगुरु की राष्ट्रवादी विचारधारा और आदरणीय नानूभाई गिड द्वारा सिखाए गए अनुशासित मूल्यों से प्रेरित होकर, मोरबी जिले भर के हजारों युवा एक मंच पर एकत्रित हुए।कॉन्क्लेव की शुरुआत में, संगठन के संस्थापक आदर्श वाक्य—जिसे स्वर्गीय श्री देवेनभाई एन. गिड (रबारी) ने बढ़ावा दिया था—&quot;प्रचार को नहीं, जरूरतमंदों को प्राथमिकता दें,&quot; को फिर से दोहराया गया। साथ ही, निकट भविष्य के लिए 79 सेवा पहलों वाला एक समयबद्ध रोडमैप भी पेश किया गया। यह रूपरेखा एक मार्गदर्शक दस्तावेज के रूप में काम करेगी, जिससे यह सुनिश्चित हो सके कि मोरबी जिले के सभी तालुकों में सेवा गतिविधियां व्यवस्थित रूप से पहुंचाई जा सकें।<br>', 'इस सम्मेलन में मोरबी शहर के साथ-साथ मालिया, हलवद, टंकारा और वांकानेर तालुका के 85 से अधिक दूर-दराज के गांवों से 3,800 से अधिक सक्रिय युवा प्रतिनिधियों और स्वयंसेवकों ने भाग लिया। कार्यक्रम की शुरुआत राष्ट्रीय ध्वज को सलामी देने और शहीदों की याद में पुष्पांजलि अर्पित करने के साथ हुई; इसके बाद, प्रतिभागियों ने सामाजिक एकता, नशा मुक्ति और नागरिक सुरक्षा से जुड़े संकल्प लिए।इस आयोजन के दौरान, एक विशेष डिजिटल नेटवर्क पेश किया गया, जिसे मोरबी जिले को रक्तदान के मामले में आत्मनिर्भर बनाने के लिए डिज़ाइन किया गया है, ताकि यहाँ चौबीसों घंटे रक्त की उपलब्धता सुनिश्चित हो सके। 1,250 से अधिक युवा रक्तदाताओं का एक डेटाबेस तैयार किया गया, ]<br>जिसे सीधे सिविल अस्पताल और विभिन्न ट्रस्ट-संचालित अस्पतालों से जोड़ा गया है, ताकि आपातकालीन स्थिति या दुर्घटनाओं के समय तुरंत रक्त उपलब्ध कराया जा सके। इसके अलावा, दूर-दराज के ग्रामीण इलाकों के लिए शैक्षिक सहायता, स्वास्थ्य जांच शिविर और महिलाओं को आत्मनिर्भर बनाने के कार्यक्रमों जैसी विभिन्न पहलों पर विस्तार से चर्चा की गई।पर्यावरण संरक्षण के प्रयासों के तहत, मोरबी के औद्योगिक और सिरेमिक बेल्ट में 1 लाख से अधिक पेड़ लगाने का संकल्प लिया गया, साथ ही अगले दो वर्षों तक उनकी निरंतर देखभाल और रखरखाव करने का भी वादा किया गया। पूरे कार्यक्रम का प्रबंधन और संचालन युवा स्वयंसेवकों द्वारा अत्यंत अनुशासित और सुनियोजित तरीके से किया गया।', 'मोरबी जिले के 85 गांवों से आए 3,800 से अधिक युवाओं द्वारा 24/7 सेवा कार्यों के लिए सं', '2026-11-14 10:40:27', '2026-11-14 11:41:50'),
(4, 2, 'gu', 'મોરબી યુવા કન્ક્લેવ: લીડરશીપ અને સામાજિક બ્લુપ્રિન્ટ', 'દેવેન રબારીના નેતૃત્વમાં સૌરાષ્ટ્રના ૬૯ ગામડાઓમાંથી ૩,૮૦૦થી વધુ યુવા આગેવાનો દ્વારા સેવા અને કટોકટી સહાય માટે ત્રિવાર્ષિક યોજનાનું નિર્ધારણ કરવામાં આવ્યું.', 'દેવેન રબારીના નેતૃત્વમાં સૌરાષ્ટ્રના ૬૯ ગામડાઓમાંથી ૩,૮૦૦થી વધુ યુવા આગેવાનો દ્વારા સેવા અને કટોકટી સહાય માટે ત્રિવાર્ષિક યોજનાનું નિર્ધારણ કરવામાં આવ્યું.<br>', 'દેવેન રબારીના નેતૃત્વમાં સૌરાષ્ટ્રના ૬૯ ગામડાઓમાંથી ૩,૮૦૦થી વધુ યુવા આગેવાનો દ્વારા સેવા અને કટોકટી સહાય માટે ત્રિવાર્ષિક યોજનાનું નિર્ધારણ કરવામાં આવ્યું.<br>', 'મોરબી જિલ્લાના ૮૫ ગામડાઓમાંથી પધારેલા ૩,૮૦૦ થી વધુ યુવાનો દ્વારા ૨૪/૭ સેવા પ્રકલ્પોની શપથ વિધિ.', '2026-11-14 10:43:09', '2026-11-14 10:43:09'),
(5, 2, 'en', 'Morbi Youth Conclave: Leadership and Social Blueprint', 'Under the leadership of Deven Rabari, a three-year plan for service and emergency assistance was formulated by over 3,800 youth leaders from 69 villages in Saurashtra.', 'Under the leadership of Deven Rabari, a three-year plan for service and emergency assistance was formulated by over 3,800 youth leaders from 69 villages in Saurashtra.', 'Under the leadership of Deven Rabari, a three-year plan for service and emergency assistance was formulated by over 3,800 youth leaders from 69 villages in Saurashtra.', 'A pledge-taking ceremony for 24/7 service initiatives by over 3,800 youths who arrived from 85 villages in the Morbi district.', '2026-11-14 10:43:09', '2026-11-14 10:43:09'),
(6, 2, 'hi', 'मोरबी जिले के 85 गांवों से आए 3,800 से अधिक युवाओं द्वारा 24/7 सेवा कार्यों के लिए संकल्प लेने का कार्यक्रम।', 'देवेन रबारी के नेतृत्व में, सौराष्ट्र के 69 गांवों के 3,800 से अधिक युवा नेताओं ने सेवा और आपातकालीन सहायता के लिए तीन साल की एक योजना तैयार की।', 'देवेन रबारी के नेतृत्व में, सौराष्ट्र के 69 गांवों के 3,800 से अधिक युवा नेताओं ने सेवा और आपातकालीन सहायता के लिए तीन साल की एक योजना तैयार की।', 'देवेन रबारी के नेतृत्व में, सौराष्ट्र के 69 गांवों के 3,800 से अधिक युवा नेताओं ने सेवा और आपातकालीन सहायता के लिए तीन साल की एक योजना तैयार की।', 'मोरबी जिले के 85 गांवों से आए 3,800 से अधिक युवाओं द्वारा 24/7 सेवा कार्यों के लिए संकल्प लेने का कार्यक्रम।', '2026-11-14 10:43:09', '2026-11-14 10:43:09');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('Super Admin','Content Editor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Content Editor',
  `status` enum('Active','Suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PHP password_hash() value. NULL until the admin sets a password. Never store plain text.',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `phone`, `role`, `status`, `password_hash`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Parth Rathod', 'parth@yig.org', '6353312905', 'Super Admin', 'Active', '$2y$10$W5gMt0fDj4qSDh0iNBIZv.aNsywGOttrG5sYrLH4FbApo44vENCP6', '2026-11-15 09:49:44', '2026-11-09 07:30:41', '2026-11-15 04:19:44'),
(3, 'soumya', 'soumya@gmail.com', '6353312905', 'Content Editor', 'Active', '$2y$10$AiTj6ZjIj9ZdoRLWUTwMYuwoF6oPOWruKDRRNSXns0tiMSfjW/igO', '2026-11-13 11:20:31', '2026-11-12 11:41:32', '2026-11-13 05:50:31');

-- --------------------------------------------------------

--
-- Table structure for table `admin_remember_tokens`
--

CREATE TABLE `admin_remember_tokens` (
  `id` int UNSIGNED NOT NULL,
  `admin_id` int UNSIGNED NOT NULL,
  `selector` char(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sha256 of the secret in the cookie',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint UNSIGNED NOT NULL,
  `admin_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Table name, e.g. donors',
  `entity_id` int UNSIGNED DEFAULT NULL,
  `details` json DEFAULT NULL COMMENT 'Old / new values',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `admin_id`, `action`, `entity`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'login_failed', 'admins', 1, '{\"email\": \"parth@yig.org\"}', '::1', '2026-11-09 08:53:54'),
(2, 1, 'login_failed', 'admins', 1, '{\"email\": \"parth@yig.org\"}', '::1', '2026-11-09 08:54:28'),
(3, 1, 'login_failed', 'admins', 1, '{\"email\": \"parth@yig.org\"}', '::1', '2026-11-09 08:54:32'),
(4, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-09 11:28:22'),
(5, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-09 17:36:21'),
(7, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-10 10:12:16'),
(8, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-10 10:13:14'),
(9, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-10 16:01:05'),
(10, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-10 17:24:58'),
(11, 1, 'login_failed', 'admins', 1, '{\"email\": \"parth@yig.org\"}', '::1', '2026-11-11 10:33:45'),
(12, 1, 'login_failed', 'admins', 1, '{\"email\": \"parth@yig.org\"}', '::1', '2026-11-11 10:33:56'),
(13, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-11 10:34:13'),
(14, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-12 05:42:52'),
(15, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-12 06:23:14'),
(16, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-12 06:26:15'),
(17, 1, 'login_failed', 'admins', 1, '{\"email\": \"parth@yig.org\"}', '::1', '2026-11-12 10:30:08'),
(18, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-12 10:30:15'),
(19, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-12 11:43:07'),
(20, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-12 11:45:06'),
(21, 3, 'login_failed', 'admins', 3, '{\"email\": \"soumya@gmail.com\"}', '::1', '2026-11-12 11:46:12'),
(22, 3, 'login_failed', 'admins', 3, '{\"email\": \"soumya@gmail.com\"}', '::1', '2026-11-12 11:46:18'),
(23, 3, 'login_failed', 'admins', 3, '{\"email\": \"soumya@gmail.com\"}', '::1', '2026-11-12 11:46:25'),
(24, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-12 11:46:46'),
(25, 3, 'login', 'admins', NULL, NULL, '::1', '2026-11-12 11:47:35'),
(26, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-13 05:39:19'),
(27, NULL, 'login_failed', 'admins', NULL, '{\"email\": \"saomya@gmail.com\"}', '::1', '2026-11-13 05:48:19'),
(28, NULL, 'login_failed', 'admins', NULL, '{\"email\": \"saomya@gmail.com\"}', '::1', '2026-11-13 05:48:29'),
(29, NULL, 'login_failed', 'admins', NULL, '{\"email\": \"saomya@gmail.com\"}', '::1', '2026-11-13 05:48:40'),
(30, NULL, 'login_failed', 'admins', NULL, '{\"email\": \"saomya@gmail.com\"}', '::1', '2026-11-13 05:48:52'),
(31, NULL, 'login_failed', 'admins', NULL, '{\"email\": \"saomya@gmail.com\"}', '::1', '2026-11-13 05:49:37'),
(32, 3, 'login', 'admins', NULL, NULL, '::1', '2026-11-13 05:50:31'),
(33, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-13 05:59:01'),
(34, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-13 10:24:39'),
(35, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-14 05:06:50'),
(36, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-14 08:51:29'),
(37, 1, 'login_failed', 'admins', 1, '{\"email\": \"parth@yig.org\"}', '::1', '2026-11-15 04:19:33'),
(38, 1, 'login_failed', 'admins', 1, '{\"email\": \"parth@yig.org\"}', '::1', '2026-11-15 04:19:38'),
(39, 1, 'login', 'admins', NULL, NULL, '::1', '2026-11-15 04:19:44');

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('Manual','Auto') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Manual',
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Where the .sql / .json file is stored on the server',
  `size_bytes` bigint UNSIGNED NOT NULL DEFAULT '0',
  `record_counts` json DEFAULT NULL COMMENT 'e.g. {"donors":45,"volunteers":32}',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blood_requests`
--

CREATE TABLE `blood_requests` (
  `id` int UNSIGNED NOT NULL,
  `patient` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `blood_group` enum('O-','O+','A-','A+','B-','B+','AB-','AB+') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `patient_condition` enum('Emergency','Normal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Normal',
  `units` tinyint UNSIGNED NOT NULL,
  `hospital_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ward` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ward / Bed',
  `contact` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Attendant phone',
  `runner` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Runner / Coordinator',
  `status` enum('Active','In-Transit','Fulfilled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `fulfilled_at` datetime DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `blood_requests`
--

INSERT INTO `blood_requests` (`id`, `patient`, `blood_group`, `patient_condition`, `units`, `hospital_address`, `ward`, `contact`, `runner`, `status`, `fulfilled_at`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 'Savita Devi Ahir', 'O-', 'Normal', 3, 'lalpar shree ram hospital', '5', '9825010548', 'Jaydeep D.', 'In-Transit', NULL, NULL, '2026-11-08 09:29:02', '2026-11-10 10:14:45'),
(5, 'other name', 'O+', 'Normal', 5, 'dftyui', '52', 'ghjk', 'hjkl', 'Active', NULL, NULL, '2026-11-09 08:05:43', '2026-11-09 08:05:43'),
(6, 'parth', 'O+', 'Emergency', 2, 'fghj', NULL, '635331', NULL, 'Active', NULL, NULL, '2026-11-10 16:36:36', '2026-11-11 10:59:03'),
(7, 'Parth Rathod', 'O+', 'Emergency', 1, 'jhtr', NULL, '6353312905', NULL, 'Active', NULL, NULL, '2026-11-12 06:06:12', '2026-11-12 06:06:12');

-- --------------------------------------------------------

--
-- Table structure for table `donors`
--

CREATE TABLE `donors` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `blood_group` enum('O-','O+','A-','A+','B-','B+','AB-','AB+') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `age` tinyint UNSIGNED NOT NULL,
  `area` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Area / Taluka',
  `last_donation` date DEFAULT NULL,
  `status` enum('Active','Inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `approved_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin who approved the website sign-up (NULL = added by an admin)',
  `approved_at` datetime DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `donors`
--

INSERT INTO `donors` (`id`, `name`, `blood_group`, `phone`, `age`, `area`, `last_donation`, `status`, `approved_by`, `approved_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'parth', 'O-', '6353312905', 55, 'Morbi City', NULL, 'Active', NULL, NULL, NULL, '2026-11-12 06:24:58', '2026-11-12 06:43:00');

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nature of inquiry',
  `status` enum('New','In Progress','Resolved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'New',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'Set only when an admin adds the inquiry by hand',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint UNSIGNED NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `success`, `created_at`) VALUES
(1, 'parth@yig.org', '::1', 0, '2026-11-09 08:53:54'),
(2, 'parth@yig.org', '::1', 0, '2026-11-09 08:54:28'),
(3, 'parth@yig.org', '::1', 0, '2026-11-09 08:54:32'),
(4, 'parth@yig.org', '::1', 1, '2026-11-09 11:28:22'),
(5, 'parth@yig.org', '::1', 1, '2026-11-09 17:36:21'),
(6, 'parth@yig.org', '::1', 1, '2026-11-10 09:28:49'),
(7, 'parth@yig.org', '::1', 1, '2026-11-10 10:12:16'),
(8, 'parth@yig.org', '::1', 1, '2026-11-10 10:13:14'),
(9, 'parth@yig.org', '::1', 1, '2026-11-10 16:01:04'),
(10, 'parth@yig.org', '::1', 1, '2026-11-10 17:24:57'),
(11, 'parth@yig.org', '::1', 0, '2026-11-11 10:33:45'),
(12, 'parth@yig.org', '::1', 0, '2026-11-11 10:33:56'),
(13, 'parth@yig.org', '::1', 1, '2026-11-11 10:34:13'),
(14, 'parth@yig.org', '::1', 1, '2026-11-12 05:42:52'),
(15, 'parth@yig.org', '::1', 1, '2026-11-12 06:23:14'),
(16, 'parth@yig.org', '::1', 1, '2026-11-12 06:26:15'),
(17, 'parth@yig.org', '::1', 0, '2026-11-12 10:30:08'),
(18, 'parth@yig.org', '::1', 1, '2026-11-12 10:30:14'),
(19, 'parth@yig.org', '::1', 1, '2026-11-12 11:43:07'),
(20, 'parth@yig.org', '::1', 1, '2026-11-12 11:45:06'),
(21, 'soumya@gmail.com', '::1', 0, '2026-11-12 11:46:11'),
(22, 'soumya@gmail.com', '::1', 0, '2026-11-12 11:46:18'),
(23, 'soumya@gmail.com', '::1', 0, '2026-11-12 11:46:25'),
(24, 'parth@yig.org', '::1', 1, '2026-11-12 11:46:46'),
(25, 'soumya@gmail.com', '::1', 1, '2026-11-12 11:47:35'),
(26, 'parth@yig.org', '::1', 1, '2026-11-13 05:39:19'),
(27, 'saomya@gmail.com', '::1', 0, '2026-11-13 05:48:19'),
(28, 'saomya@gmail.com', '::1', 0, '2026-11-13 05:48:29'),
(29, 'saomya@gmail.com', '::1', 0, '2026-11-13 05:48:40'),
(30, 'saomya@gmail.com', '::1', 0, '2026-11-13 05:48:51'),
(31, 'saomya@gmail.com', '::1', 0, '2026-11-13 05:49:37'),
(32, 'soumya@gmail.com', '::1', 1, '2026-11-13 05:50:30'),
(33, 'parth@yig.org', '::1', 1, '2026-11-13 05:59:01'),
(34, 'parth@yig.org', '::1', 1, '2026-11-13 10:24:39'),
(35, 'parth@yig.org', '::1', 1, '2026-11-14 05:06:50'),
(36, 'parth@yig.org', '::1', 1, '2026-11-14 08:51:29'),
(37, 'parth@yig.org', '::1', 0, '2026-11-15 04:19:33'),
(38, 'parth@yig.org', '::1', 0, '2026-11-15 04:19:38'),
(39, 'parth@yig.org', '::1', 1, '2026-11-15 04:19:44');

-- --------------------------------------------------------

--
-- Table structure for table `notification_reads`
--

CREATE TABLE `notification_reads` (
  `admin_id` int UNSIGNED NOT NULL,
  `item_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. blood:12, inq:7, pend:d:3, backup:none',
  `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_donors`
--

CREATE TABLE `pending_donors` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `blood_group` enum('O-','O+','A-','A+','B-','B+','AB-','AB+') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `age` tinyint UNSIGNED NOT NULL,
  `area` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_donation` date DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_volunteers`
--

CREATE TABLE `pending_volunteers` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `area` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `availability` enum('Weekend','Emergency SOS','4-8 hrs / week') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `volunteers`
--

CREATE TABLE `volunteers` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `area` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Area / Ward',
  `role` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Primary role',
  `availability` enum('Anytime','Weekend','Emergency SOS','4-8 hrs / week') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Active','Inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Inactive',
  `approved_by` int UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `volunteers`
--

INSERT INTO `volunteers` (`id`, `name`, `phone`, `area`, `role`, `availability`, `status`, `approved_by`, `approved_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'parth', '6353312905', 'Shanala Road', 'Education & Mentorship', 'Anytime', 'Active', NULL, NULL, NULL, '2026-11-12 07:20:41', '2026-11-13 05:51:27');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_activities`
-- (See below for the actual view)
--
CREATE TABLE `v_activities` (
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_admins`
-- (See below for the actual view)
--
CREATE TABLE `v_admins` (
`code` varchar(8)
,`id` int unsigned
,`name` varchar(100)
,`email` varchar(150)
,`phone` varchar(20)
,`role` enum('Super Admin','Content Editor')
,`status` enum('Active','Suspended')
,`last_login_at` datetime
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_blood_requests`
-- (See below for the actual view)
--
CREATE TABLE `v_blood_requests` (
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_dashboard_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_dashboard_stats` (
`total_volunteers` bigint
,`verified_donors` bigint
,`urgent_blood` bigint
,`lives_saved` bigint
,`civic_drives` bigint
,`pending_donors` bigint
,`pending_volunteers` bigint
,`new_inquiries` bigint
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_donors`
-- (See below for the actual view)
--
CREATE TABLE `v_donors` (
`code` varchar(8)
,`id` int unsigned
,`name` varchar(100)
,`blood_group` enum('O-','O+','A-','A+','B-','B+','AB-','AB+')
,`phone` varchar(20)
,`age` tinyint unsigned
,`area` varchar(60)
,`last_donation` date
,`status` enum('Active','Inactive')
,`approved_by` int unsigned
,`approved_at` datetime
,`created_by` int unsigned
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_inquiries`
-- (See below for the actual view)
--
CREATE TABLE `v_inquiries` (
`code` varchar(8)
,`id` int unsigned
,`name` varchar(100)
,`phone` varchar(20)
,`type` varchar(60)
,`status` enum('New','In Progress','Resolved')
,`message` text
,`ip_address` varchar(45)
,`created_by` int unsigned
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_pending_donors`
-- (See below for the actual view)
--
CREATE TABLE `v_pending_donors` (
`code` varchar(16)
,`id` int unsigned
,`name` varchar(100)
,`blood_group` enum('O-','O+','A-','A+','B-','B+','AB-','AB+')
,`phone` varchar(20)
,`age` tinyint unsigned
,`area` varchar(60)
,`last_donation` date
,`ip_address` varchar(45)
,`submitted_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_pending_volunteers`
-- (See below for the actual view)
--
CREATE TABLE `v_pending_volunteers` (
`code` varchar(16)
,`id` int unsigned
,`name` varchar(100)
,`phone` varchar(20)
,`area` varchar(60)
,`role` varchar(80)
,`availability` enum('Weekend','Emergency SOS','4-8 hrs / week')
,`ip_address` varchar(45)
,`submitted_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_volunteers`
-- (See below for the actual view)
--
CREATE TABLE `v_volunteers` (
`code` varchar(8)
,`id` int unsigned
,`name` varchar(100)
,`phone` varchar(20)
,`area` varchar(60)
,`role` varchar(80)
,`availability` enum('Anytime','Weekend','Emergency SOS','4-8 hrs / week')
,`status` enum('Active','Inactive')
,`approved_by` int unsigned
,`approved_at` datetime
,`created_by` int unsigned
,`created_at` timestamp
,`updated_at` timestamp
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_act_status_date` (`status`,`activity_date`),
  ADD KEY `idx_act_category` (`category`),
  ADD KEY `idx_act_area` (`area`),
  ADD KEY `idx_act_title` (`title`),
  ADD KEY `fk_act_created_by` (`created_by`);

--
-- Indexes for table `activity_gallery_images`
--
ALTER TABLE `activity_gallery_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_gallery_activity_order` (`activity_id`,`sort_order`);

--
-- Indexes for table `activity_translations`
--
ALTER TABLE `activity_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_activity_language` (`activity_id`,`language`),
  ADD KEY `idx_translation_title` (`title`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admins_email` (`email`),
  ADD KEY `idx_admins_status` (`status`);

--
-- Indexes for table `admin_remember_tokens`
--
ALTER TABLE `admin_remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_remember_selector` (`selector`),
  ADD KEY `idx_remember_admin` (`admin_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_entity` (`entity`,`entity_id`),
  ADD KEY `idx_audit_admin` (`admin_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_backups_created` (`created_at`),
  ADD KEY `fk_backups_created_by` (`created_by`);

--
-- Indexes for table `blood_requests`
--
ALTER TABLE `blood_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blood_status_created` (`status`,`created_at`),
  ADD KEY `idx_blood_group` (`blood_group`),
  ADD KEY `idx_blood_hospital` (`hospital_address`),
  ADD KEY `idx_blood_patient` (`patient`),
  ADD KEY `fk_blood_created_by` (`created_by`);

--
-- Indexes for table `donors`
--
ALTER TABLE `donors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_donors_group_area` (`blood_group`,`area`),
  ADD KEY `idx_donors_status` (`status`),
  ADD KEY `idx_donors_phone` (`phone`),
  ADD KEY `idx_donors_name` (`name`),
  ADD KEY `fk_donors_approved_by` (`approved_by`),
  ADD KEY `fk_donors_created_by` (`created_by`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inq_status_created` (`status`,`created_at`),
  ADD KEY `idx_inq_type` (`type`),
  ADD KEY `idx_inq_phone` (`phone`),
  ADD KEY `fk_inq_created_by` (`created_by`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_la_email_time` (`email`,`created_at`),
  ADD KEY `idx_la_ip_time` (`ip_address`,`created_at`);

--
-- Indexes for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`admin_id`,`item_key`);

--
-- Indexes for table `pending_donors`
--
ALTER TABLE `pending_donors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pdonor_submitted` (`submitted_at`),
  ADD KEY `idx_pdonor_phone` (`phone`);

--
-- Indexes for table `pending_volunteers`
--
ALTER TABLE `pending_volunteers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pvol_submitted` (`submitted_at`),
  ADD KEY `idx_pvol_phone` (`phone`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `volunteers`
--
ALTER TABLE `volunteers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vol_status_area` (`status`,`area`),
  ADD KEY `idx_vol_role` (`role`),
  ADD KEY `idx_vol_phone` (`phone`),
  ADD KEY `idx_vol_name` (`name`),
  ADD KEY `fk_vol_approved_by` (`approved_by`),
  ADD KEY `fk_vol_created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `activity_gallery_images`
--
ALTER TABLE `activity_gallery_images`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `activity_translations`
--
ALTER TABLE `activity_translations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin_remember_tokens`
--
ALTER TABLE `admin_remember_tokens`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blood_requests`
--
ALTER TABLE `blood_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `donors`
--
ALTER TABLE `donors`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `pending_donors`
--
ALTER TABLE `pending_donors`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pending_volunteers`
--
ALTER TABLE `pending_volunteers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `volunteers`
--
ALTER TABLE `volunteers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- --------------------------------------------------------

--
-- Structure for view `v_activities`
--
DROP TABLE IF EXISTS `v_activities`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_activities`  AS SELECT concat('ACT-',lpad(`t`.`id`,4,'0')) AS `code`, `t`.`id` AS `id`, `t`.`title` AS `title`, `t`.`category` AS `category`, `t`.`area` AS `area`, `t`.`activity_date` AS `activity_date`, `t`.`metric` AS `metric`, `t`.`status` AS `status`, `t`.`description` AS `description`, `t`.`created_by` AS `created_by`, `t`.`created_at` AS `created_at`, `t`.`updated_at` AS `updated_at` FROM `activities` AS `t` ;

-- --------------------------------------------------------

--
-- Structure for view `v_admins`
--
DROP TABLE IF EXISTS `v_admins`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_admins`  AS SELECT concat('ADM-',lpad(`t`.`id`,4,'0')) AS `code`, `t`.`id` AS `id`, `t`.`name` AS `name`, `t`.`email` AS `email`, `t`.`phone` AS `phone`, `t`.`role` AS `role`, `t`.`status` AS `status`, `t`.`last_login_at` AS `last_login_at`, `t`.`created_at` AS `created_at`, `t`.`updated_at` AS `updated_at` FROM `admins` AS `t` ;

-- --------------------------------------------------------

--
-- Structure for view `v_blood_requests`
--
DROP TABLE IF EXISTS `v_blood_requests`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_blood_requests`  AS SELECT concat('REQ-',lpad(`t`.`id`,4,'0')) AS `code`, `t`.`id` AS `id`, `t`.`patient` AS `patient`, `t`.`blood_group` AS `blood_group`, `t`.`units` AS `units`, `t`.`hospital` AS `hospital`, `t`.`ward` AS `ward`, `t`.`contact` AS `contact`, `t`.`runner` AS `runner`, `t`.`status` AS `status`, `t`.`fulfilled_at` AS `fulfilled_at`, `t`.`created_by` AS `created_by`, `t`.`created_at` AS `created_at`, `t`.`updated_at` AS `updated_at` FROM `blood_requests` AS `t` ;

-- --------------------------------------------------------

--
-- Structure for view `v_dashboard_stats`
--
DROP TABLE IF EXISTS `v_dashboard_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_dashboard_stats`  AS SELECT (select count(0) from `volunteers`) AS `total_volunteers`, (select count(0) from `donors`) AS `verified_donors`, (select count(0) from `blood_requests` where (`blood_requests`.`status` = 'Active')) AS `urgent_blood`, (select count(0) from `blood_requests` where (`blood_requests`.`status` = 'Fulfilled')) AS `lives_saved`, (select count(0) from `activities`) AS `civic_drives`, (select count(0) from `pending_donors`) AS `pending_donors`, (select count(0) from `pending_volunteers`) AS `pending_volunteers`, (select count(0) from `inquiries` where (`inquiries`.`status` = 'New')) AS `new_inquiries` ;

-- --------------------------------------------------------

--
-- Structure for view `v_donors`
--
DROP TABLE IF EXISTS `v_donors`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_donors`  AS SELECT concat('DNR-',lpad(`t`.`id`,4,'0')) AS `code`, `t`.`id` AS `id`, `t`.`name` AS `name`, `t`.`blood_group` AS `blood_group`, `t`.`phone` AS `phone`, `t`.`age` AS `age`, `t`.`area` AS `area`, `t`.`last_donation` AS `last_donation`, `t`.`status` AS `status`, `t`.`approved_by` AS `approved_by`, `t`.`approved_at` AS `approved_at`, `t`.`created_by` AS `created_by`, `t`.`created_at` AS `created_at`, `t`.`updated_at` AS `updated_at` FROM `donors` AS `t` ;

-- --------------------------------------------------------

--
-- Structure for view `v_inquiries`
--
DROP TABLE IF EXISTS `v_inquiries`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_inquiries`  AS SELECT concat('INQ-',lpad(`t`.`id`,4,'0')) AS `code`, `t`.`id` AS `id`, `t`.`name` AS `name`, `t`.`phone` AS `phone`, `t`.`type` AS `type`, `t`.`status` AS `status`, `t`.`message` AS `message`, `t`.`ip_address` AS `ip_address`, `t`.`created_by` AS `created_by`, `t`.`created_at` AS `created_at`, `t`.`updated_at` AS `updated_at` FROM `inquiries` AS `t` ;

-- --------------------------------------------------------

--
-- Structure for view `v_pending_donors`
--
DROP TABLE IF EXISTS `v_pending_donors`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pending_donors`  AS SELECT concat('TMP-D-',`t`.`id`) AS `code`, `t`.`id` AS `id`, `t`.`name` AS `name`, `t`.`blood_group` AS `blood_group`, `t`.`phone` AS `phone`, `t`.`age` AS `age`, `t`.`area` AS `area`, `t`.`last_donation` AS `last_donation`, `t`.`ip_address` AS `ip_address`, `t`.`submitted_at` AS `submitted_at` FROM `pending_donors` AS `t` ;

-- --------------------------------------------------------

--
-- Structure for view `v_pending_volunteers`
--
DROP TABLE IF EXISTS `v_pending_volunteers`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pending_volunteers`  AS SELECT concat('TMP-V-',`t`.`id`) AS `code`, `t`.`id` AS `id`, `t`.`name` AS `name`, `t`.`phone` AS `phone`, `t`.`area` AS `area`, `t`.`role` AS `role`, `t`.`availability` AS `availability`, `t`.`ip_address` AS `ip_address`, `t`.`submitted_at` AS `submitted_at` FROM `pending_volunteers` AS `t` ;

-- --------------------------------------------------------

--
-- Structure for view `v_volunteers`
--
DROP TABLE IF EXISTS `v_volunteers`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_volunteers`  AS SELECT concat('VOL-',lpad(`t`.`id`,4,'0')) AS `code`, `t`.`id` AS `id`, `t`.`name` AS `name`, `t`.`phone` AS `phone`, `t`.`area` AS `area`, `t`.`role` AS `role`, `t`.`availability` AS `availability`, `t`.`status` AS `status`, `t`.`approved_by` AS `approved_by`, `t`.`approved_at` AS `approved_at`, `t`.`created_by` AS `created_by`, `t`.`created_at` AS `created_at`, `t`.`updated_at` AS `updated_at` FROM `volunteers` AS `t` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `fk_act_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `activity_gallery_images`
--
ALTER TABLE `activity_gallery_images`
  ADD CONSTRAINT `fk_activity_gallery_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `activity_translations`
--
ALTER TABLE `activity_translations`
  ADD CONSTRAINT `fk_translation_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `admin_remember_tokens`
--
ALTER TABLE `admin_remember_tokens`
  ADD CONSTRAINT `fk_remember_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `backups`
--
ALTER TABLE `backups`
  ADD CONSTRAINT `fk_backups_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `blood_requests`
--
ALTER TABLE `blood_requests`
  ADD CONSTRAINT `fk_blood_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `donors`
--
ALTER TABLE `donors`
  ADD CONSTRAINT `fk_donors_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_donors_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD CONSTRAINT `fk_inq_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD CONSTRAINT `fk_notif_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `volunteers`
--
ALTER TABLE `volunteers`
  ADD CONSTRAINT `fk_vol_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vol_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
